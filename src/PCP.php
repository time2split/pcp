<?php
namespace Time2Split\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\IO;
use Time2Split\PCP\Action\ActionFactory;
use Time2Split\PCP\Action\IAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PhaseData\ReadingDirectory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\CPPDirectives;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\PCP\DataFlow\BasePublisher;

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

    private ?IAction $monopolyFor = null;

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
            $this->processDir($dir, $config);

        $this->updatePhase( //
        PhaseName::ProcessingFiles, //
        PhaseState::Stop //
        );
    }

    private $newFiles = [];

    public function processDir(\SplFileInfo|string $wdir, Configuration $parentConfig): void
    {
        $phaseData = ReadingDirectory::fromPath($wdir);
        $this->updatePhase( //
        PhaseName::OpeningDirectory, //
        PhaseState::Start, //
        $phaseData);

        $searchConfigFiles = (array) $parentConfig['pcp.reading.dir.configFiles'];
        $dirConfig = Configurations::emptyChild($parentConfig);
        $this->setSubscribersConfig($dirConfig);

        foreach ($searchConfigFiles as $searchForFile) {
            $searchForFile = new \SplFileInfo("$wdir/$searchForFile");

            if (\is_file($searchForFile))
                $this->processOneCFile($searchForFile, $dirConfig);
        }

        $this->updatePhase( //
        PhaseName::OpeningDirectory, //
        PhaseState::Run, //
        $phaseData);

        $it = new \FileSystemIterator($wdir);
        $dirs = [];

        $fileConfig = Configurations::emptyChild($dirConfig);
        $this->setSubscribersConfig($fileConfig);

        loop:
        foreach ($it as $finfo) {

            if ($finfo->isDir())
                $dirs[] = $finfo;
            else {
                $fileConfig->clear();
                $this->processOneFile($finfo, $fileConfig);
            }
        }
        // Iterate through new files
        if (! empty($this->newFiles)) {
            $it = $this->newFiles;
            $this->newFiles = [];
            goto loop;
        }

        foreach ($dirs as $d)
            $this->processDir($d, $dirConfig);

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

    private function expandAtConfig(PCPPragma $pcpPragma, Configuration $fileConfig): PCPPragma
    {
        $updated = false;
        $pcpArguments = $pcpPragma->getArguments();
        $newConfig = Configurations::emptyCopyOf($pcpArguments);

        foreach ($pcpArguments->getRawValueIterator() as $k => $v) {

            if (! \str_starts_with($k, '@config')) {
                $newConfig[$k] = $v;
                continue;
            }
            $nextConfig = $fileConfig->getOptional($k, false);

            if ($nextConfig->isPresent($k)) {

                if (! $updated && $k === '@config')
                    $updated = true;

                $newConfig->merge($nextConfig->get()
                    ->getRawValueIterator());
            }
        }

        if (! $updated) {
            $nextConfig = $fileConfig->getOptional('@config');

            if ($nextConfig->isPresent())
                $newConfig->merge($nextConfig->get()
                    ->getRawValueIterator());
        }
        return $pcpPragma->copy($newConfig);
    }

    private function processOneCFile(\SplFileInfo $finfo, Configuration $fileConfig): void
    {
        $phaseData = ReadingOneFile::fromPath($finfo);
        $this->updatePhase( //
        PhaseName::ReadingOneFile, //
        PhaseState::Start, //
        $phaseData);

        $creader = CReader::fromFile($finfo);
        $creader->setCPPDirectiveFactory(CPPDirectives::factory($fileConfig));
        $skip = false;
        $elements = [];

        try {
            while (true) {

                if (! empty($elements))
                    $element = \array_pop($elements);
                else {
                    $this->updatePhase(PhaseName::ReadingCElement, PhaseState::Start);
                    $element = $creader->next();

                    if (null === $element)
                        break;
                }

                if (CContainer::of($element)->isPCPPragma()) {
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

                    if (! isset($this->monopolyFor) || ! $this->monopolyFor->noExpandAtConfig())
                        $element = $this->expandAtConfig($element, $fileConfig);
                }

                {
                    // Set C information(s)
                    $fileConfig['C.type'] = $element->getElementType($element)->value;
                }

                if (! $skip) {
                    $resElements = $this->deliverMessage(CContainer::of($element));

                    if (! empty($resElements)) {
                        // Reverse the order to allow to array_pop($elements) in the original order
                        $elements = \array_merge($elements, \array_reverse($resElements));
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("File $finfo position {$creader->getCursorPosition()}", previous: $e);
        }
        $creader->close();
        $this->updatePhase( //
        PhaseName::ReadingOneFile, //
        PhaseState::Stop, //
        $phaseData);
    }
}
