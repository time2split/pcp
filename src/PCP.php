<?php
namespace Time2Split\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\IO;
use Time2Split\PCP\Action\ActionFactory;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PhaseData\ReadingDirectory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\CPPDirectives;
use Time2Split\PCP\DataFlow\BasePublisher;
use Time2Split\PCP\DataFlow\ISubscriber;

/**
 * PHP: C preprocessor
 *
 * @author zuri
 * @date 02/07/2022 12:36:56 CEST
 */
class PCP extends BasePublisher
{

    public function __construct()
    {
        parent::__construct();
    }

    private ?ISubscriber $monopolyFor = null;

    private function deliverMessage(CContainer $container): array
    {
        $resElements = [];
        $monopoly = [];

        if (isset($this->monopolyFor))
            $subscribers = [
                $this->monopolyFor
            ];
        else
            $subscribers = $this->getSubscribers();

        foreach ($subscribers as $s) {
            $this->monopolyFor = null;
            $resElements = \array_merge($resElements, $s->onMessage($container));

            if ($s->hasMonopoly())
                $monopoly[] = $s;
        }
        $nbMonopoly = \count($monopoly);

        if ($nbMonopoly > 1)
            throw new \Exception('Multiple actions had asked for monopoly');
        if ($nbMonopoly === 1)
            $this->monopolyFor = $monopoly[0];

        return $resElements;
    }

    private function setSubscribersConfig(Configuration $config): array
    {
        $resElements = [];

        foreach ($this->getSubscribers() as $s)
            $s->setConfig($config);

        return $resElements;
    }

    private function updatePhase(PhaseName $name, PhaseState $state, $data = null): void
    {
        foreach ($this->getSubscribers() as $s)
            $s->onPhase(Phase::create($name, $state), $data);
    }

    public function process(Configuration $config): void
    {
        $actions = ActionFactory::get($config)->getActions();
        \array_walk($actions, $this->subscribe(...));

        // Init and check phase
        {
            $wd = $config['pcp.dir'];

            if (! is_dir($wd))
                \mkdir($wd, 0777, true);
        }

        $this->updatePhase( //
        PhaseName::ProcessingFiles, //
        PhaseState::Start //
        );

        $config['dateTime'] = $date = new \DateTime();
        $config['dateTime.format'] = $date->format(\DateTime::ATOM);

        foreach ($config['paths'] as $dir)
            $this->processDir($dir, Configurations::emptyChild($config));

        $this->updatePhase( //
        PhaseName::ProcessingFiles, //
        PhaseState::Stop //
        );
    }

    private $newFiles = [];

    public function processDir($wdir, Configuration $config): void
    {
        $phaseData = ReadingDirectory::fromPath($wdir);
        $this->updatePhase( //
        PhaseName::OpeningDirectory, //
        PhaseState::Start, //
        $phaseData);

        $searchConfigFiles = (array) $config['pcp.reading.dir.configFiles'];
        $config = Configurations::emptyChild($config);
        $this->setSubscribersConfig($config);

        foreach ($searchConfigFiles as $searchForFile) {
            $searchForFile = new \SplFileInfo("$wdir/$searchForFile");

            if (\is_file($searchForFile)) {
                $this->processOneCFile($searchForFile, $config);
            }
        }

        $this->updatePhase( //
        PhaseName::OpeningDirectory, //
        PhaseState::Run, //
        $phaseData);

        $it = new \FileSystemIterator($wdir);
        $dirs = [];

        $config = Configurations::emptyChild($config);
        $this->setSubscribersConfig($config);

        loop:
        foreach ($it as $finfo) {

            if ($finfo->isDir())
                $dirs[] = $finfo;
            else
                $this->processOneFile($finfo, $config);
        }
        // Iterate through new files
        if (! empty($this->newFiles)) {
            $it = $this->newFiles;
            $this->newFiles = [];
            goto loop;
        }

        foreach ($dirs as $d)
            $this->processDir($d, $config);

        $this->updatePhase( //
        PhaseName::OpeningDirectory, //
        PhaseState::Stop, //
        $phaseData);
    }

    private function processOneFile(\SplFileInfo $finfo, Configuration $config): void
    {
        if (\str_ends_with($finfo, '.php')) {
            $newFile = \substr($finfo, 0, - 4);
            $notFile = ! \is_file($newFile);

            if ($notFile || IO::olderThan($newFile, $finfo))
                \file_put_contents($newFile, IO::get_include_contents($finfo));

            // A new file to consider is created
            if ($notFile)
                $this->newFiles[] = new \SplFileInfo($newFile);

            return;
        }

        if (! \in_array(\substr($finfo, - 2), [
            '.h',
            '.c'
        ]))
            return;

        $this->processOneCFile($finfo, $config);
    }

    private function processOneCFile(\SplFileInfo $finfo, Configuration $config): void
    {
        $phaseData = ReadingOneFile::fromPath($finfo);
        $this->updatePhase( //
        PhaseName::ReadingOneFile, //
        PhaseState::Start, //
        $phaseData);

        $creader = CReader::fromFile($finfo);
        $creader->setCPPDirectiveFactory(CPPDirectives::factory($config));
        $skip = false;
        $elements = [];

        while (true) {

            if (! empty($elements))
                $element = \array_pop($elements);
            else {
                $this->updatePhase(PhaseName::ReadingCElement, PhaseState::Start);
                $element = $creader->next();

                if (null === $element)
                    break;
            }

            $container = CContainer::of($element);

            if ($container->isPCPPragma()) {
                $cmd = $element->getCommand();

                // Avoid begin/end blocks
                if ($skip) {

                    if ($cmd === 'end') {
                        $skip = false;
                        continue;
                    }
                } elseif ($cmd === 'begin') {
                    $skip = true;
                    continue;
                }
            }

            {
                // Set C information(s)
                $config['C.type'] = $element->getElementType($element)->value;
            }

            if (! $skip) {
                $resElements = $this->deliverMessage($container);

                if (! empty($resElements)) {
                    // Reverse the order to allow to array_pop($elements) in the original order
                    $elements = \array_merge($elements, \array_reverse($resElements));
                }
            }
        }
        $creader->close();
        $this->updatePhase( //
        PhaseName::ReadingOneFile, //
        PhaseState::Stop, //
        $phaseData);
    }
}
