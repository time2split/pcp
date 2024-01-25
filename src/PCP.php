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
use Time2Split\PCP\DataFlow\BasePublisher;
use Time2Split\PCP\C\Element\CPPDirectives;

/**
 * PHP: C preprocessor
 *
 * @author zuri
 * @date 02/07/2022 12:36:56 CEST
 */
class PCP extends BasePublisher
{

    private Configuration $config;

    public function __construct()
    {
        parent::__construct();
    }

    private function deliverMessage(CContainer $container): array
    {
        $resElements = [];

        foreach ($this->getSubscribers() as $s)
            $resElements = \array_merge($resElements, $s->onMessage($container));

        return $resElements;
    }

    private function updatePhase(PhaseName $name, PhaseState $state, $data = null)
    {
        foreach ($this->getSubscribers() as $s)
            $s->onPhase(Phase::create($name, $state), $data);
    }

    public function process(Configuration $config): void
    {
        $this->config = Configurations::emptyChild($config);
        $this->config['dateTime'] = $date = new \DateTime();
        $this->config['dateTime.format'] = $date->format(\DateTime::ATOM);

        $actions = ActionFactory::get($this->config)->getActions();
        \array_walk($actions, $this->subscribe(...));

        // Init and check phase
        {
            $wd = $this->config['cpp.wd'];

            if (! is_dir($wd))
                \mkdir($wd, 0777, true);
        }

        $this->updatePhase( //
        PhaseName::ProcessingFiles, //
        PhaseState::Start //
        );

        $this->updatePhase( //
        PhaseName::ProcessingFiles, //
        PhaseState::Run //
        );

        foreach ($config['paths'] as $dir)
            $this->processDir($dir);

        $this->updatePhase( //
        PhaseName::ProcessingFiles, //
        PhaseState::Stop //
        );
    }

    private $newFiles = [];

    public function processDir($wdir): void
    {
        $phaseData = ReadingDirectory::fromPath($wdir);
        $this->updatePhase( //
        PhaseName::OpeningDirectory, //
        PhaseState::Start, //
        $phaseData);

        $it = new \FileSystemIterator($wdir);
        $dirs = [];

        loop:
        foreach ($it as $finfo) {

            if ($finfo->isDir())
                $dirs[] = $finfo;
            else
                $this->processOneFile($finfo);
        }
        // Iterate through new files
        if (! empty($this->newFiles)) {
            $it = $this->newFiles;
            $this->newFiles = [];
            goto loop;
        }

        foreach ($dirs as $d)
            $this->processDir($d);

        $this->updatePhase( //
        PhaseName::OpeningDirectory, //
        PhaseState::Stop, //
        $phaseData);
    }

    private function processOneFile(\SplFileInfo $finfo): void
    {
        if (\str_ends_with($finfo, '.php')) {
            $newFile = \substr($finfo, 0, - 4);
            $notFile = ! \is_file($newFile);

            if ($notFile || IO::olderThan($newFile, $finfo))
                \file_put_contents($newFile, IO::get_include_contents($finfo));

            // A new file to consider is create
            if ($notFile)
                $this->newFiles[] = new \SplFileInfo($newFile);

            return;
        }

        if (! \in_array(\substr($finfo, - 2), [
            '.h',
            '.c'
        ]))
            return;

        $phaseData = ReadingOneFile::fromPath($finfo);
        $this->updatePhase( //
        PhaseName::ReadingOneFile, //
        PhaseState::Start, //
        $phaseData);

        $creader = CReader::fromFile($finfo);
        $creader->setCPPDirectiveFactory(CPPDirectives::factory($this->config));
        $skip = false;

        $this->updatePhase( //
        PhaseName::ReadingOneFile, //
        PhaseState::Run //
        );
        $elements = [];

        while (true) {

            if (! empty($elements))
                $element = \array_pop($elements);
            else if (null === ($element = $creader->next()))
                break;

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
            if (! $skip) {
                $resElements = $this->deliverMessage($container);

                // Reverse the order to allow to array_pop($elements) in the original order
                $elements = \array_merge($elements, \array_reverse($resElements));
            }
        }
        $creader->close();
        $this->updatePhase( //
        PhaseName::ReadingOneFile, //
        PhaseState::Stop, //
        $phaseData);
    }
}
