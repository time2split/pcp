<?php

declare(strict_types=1);

namespace Time2Split\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Config\Entry\ReadingMode;
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
use Time2Split\PCP\C\Element\CElementType;
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

    /**
     * Process the files inside the current working directory.
     */
    public function process(string $action, Configuration $config): void
    {
        $config['dir.root'] = \getcwd();

        $actions = ActionFactory::get($config)->getActions($action);
        \array_walk($actions, $this->subscribe(...));

        // Init and check phase
        {
            $wd = $config['pcp.dir'];

            if (!is_dir($wd))
                \mkdir($wd, 0777, true);
        }

        $this->updatePhase(
            PhaseName::ProcessingFiles,
            PhaseState::Start
        );

        $config['dateTime'] = $date = new \DateTime();
        $config['dateTime.format'] = $date->format(\DateTime::ATOM);

        foreach ((array)$config['paths'] as $dir)
            $this->processDir($dir, $config);


        $this->updatePhase(
            PhaseName::ProcessingFiles,
            PhaseState::Stop
        );
    }

    private $newFiles = [];

    private function processDir(\SplFileInfo|string $wdir, Configuration $parentConfig): void
    {
        $phaseData = ReadingDirectory::fromPath($wdir);

        $this->updatePhase(
            PhaseName::OpeningDirectory,
            PhaseState::Start,
            $phaseData
        );

        $searchConfigFiles = (array) $parentConfig['pcp.reading.dir.configFiles'];
        $dirConfig = Configurations::emptyChild($parentConfig);
        $this->setSubscribersConfig($dirConfig);

        foreach ($searchConfigFiles as $searchForFile) {
            $searchForFile = "$wdir/$searchForFile";

            if (\is_file($searchForFile))
                $this->processOneCFile($searchForFile, $dirConfig);
        }

        $this->updatePhase(
            PhaseName::OpeningDirectory,
            PhaseState::Run,
            $phaseData
        );

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
                $this->processOneFile($finfo->getPathName(), $fileConfig);
            }
        }
        // Iterate through new files
        if (!empty($this->newFiles)) {
            $it = $this->newFiles;
            $this->newFiles = [];
            goto loop;
        }

        foreach ($dirs as $d)
            $this->processDir($d, $dirConfig);

        $this->updatePhase(
            PhaseName::OpeningDirectory,
            PhaseState::Stop,
            $phaseData
        );
    }

    private function processOneFile(string $fname, Configuration $config): void
    {
        if (\str_ends_with($fname, '.php')) {
            $newFile = \substr($fname, 0, -4);
            $notFile = !\is_file($newFile);

            if ($notFile || IO::olderThan($newFile, $fname))
                \file_put_contents($newFile, IO::get_include_contents($fname));

            // A new file to consider is created
            if ($notFile)
                $this->newFiles[] = new \SplFileInfo($newFile);
        } elseif (\in_array(\substr($fname, -2), [
            '.h',
            '.c'
        ]))
            $this->processOneCFile($fname, $config);
    }

    private function expandAtConfig(PCPPragma $pcpPragma, Configuration $fileConfig): PCPPragma
    {
        $updated = false;
        $pcpArguments = $pcpPragma->getArguments();
        $newConfig = Configurations::emptyTreeCopyOf($pcpArguments);

        foreach ($pcpArguments->getRawValueIterator() as $k => $v) {

            if (!\str_starts_with($k, '@config')) {
                $newConfig[$k] = $v;
                continue;
            }
            $nextConfig = $fileConfig->getOptional($k, ReadingMode::RawValue);

            if ($nextConfig->isPresent($k)) {

                if (!$updated && $k === '@config')
                    $updated = true;

                $newConfig->merge($nextConfig->get()
                    ->getRawValueIterator());
            }
        }

        if (!$updated) {
            $nextConfig = $fileConfig->getOptional('@config');

            if ($nextConfig->isPresent())
                $newConfig->merge($nextConfig->get()
                    ->getRawValueIterator());
        }
        return $pcpPragma->copy($newConfig);
    }

    private function processOneCFile(string $fname, Configuration $fileConfig): void
    {
        $phaseData = ReadingOneFile::fromPath($fname);
        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Start,
            $phaseData
        );

        $creader = CReader::fromFile($fname);
        $creader->setCPPDirectiveFactory(CPPDirectives::factory($fileConfig));
        $elements = [];

        try {
            while (true) {

                if (!empty($elements))
                    $element = \array_pop($elements);
                else {
                    $this->updatePhase(PhaseName::ReadingCElement, PhaseState::Start);
                    $element = $creader->next();

                    if (null === $element)
                        break;
                }

                if (CContainer::of($element)->isPCPPragma()) {

                    if (!isset($this->monopolyFor) || !$this->monopolyFor->noExpandAtConfig())
                        $element = $this->expandAtConfig($element, $fileConfig);
                } {
                    $ctypes = $element->getElementType($element);
                    // Set C informations
                    // $fileConfig['C.types'] = $ctypes;

                    if ($ctypes[CElementType::Function]) {
                        $fileConfig['C.specifiers'] = $element->getSpecifiers();
                        $fileConfig['C.identifier'] = $element->getIdentifier();
                    }
                }

                $resElements = $this->deliverMessage(CContainer::of($element));

                if (!empty($resElements)) {
                    // Reverse the order to allow to array_pop($elements) in the original order
                    $elements = \array_merge($elements, \array_reverse($resElements));
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("File $fname position {$creader->getCursorPosition()}", previous: $e);
        }
        $creader->close();
        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Stop,
            $phaseData
        );
    }
}
