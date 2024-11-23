<?php

declare(strict_types=1);

namespace Time2Split\PCP;

use SplFileInfo;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Config\Entry\ReadingMode;
use Time2Split\Help\IO;
use Time2Split\PCP\Action\Actions;
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
use Time2Split\PCP\Help\HelpIterables;

/**
 * PHP: C preprocessor
 *
 * @author zuri
 * @date 02/07/2022 12:36:56 CEST
 */
class PCP extends BasePublisher
{
    private const array DefaultConfig = [
        'pcp' => [
            'action' => null,
            'dir.root' => null,
            'dir' => 'pcp.wd',
            'reading.dir.configFiles' => 'pcp.conf',
            'pragma.names' => 'pcp',
            'paths' => [
                'src'
            ],
        ]
    ];

    private Configuration $actionsConfig;

    public function __construct(Configuration $appConfig)
    {
        parent::__construct();
        $this->actionsConfig = self::makeActionsConfig($appConfig);

        // Subscribe the actions
        $actions = Actions::factory($this->actionsConfig)
            ->getActions($appConfig['pcp.action']);

        if (empty($actions))
            throw new \Exception("Unknown action '{$appConfig['pcp.action']}'");

        \array_walk($actions, $this->subscribe(...));
    }

    private static function makeActionsConfig(Configuration $appConfig): Configuration
    {
        return Configurations::hierarchy(
            App::emptyConfiguration()->mergeTree(self::DefaultConfig),
            $appConfig,
            App::emptyConfiguration()
        );
    }

    public function creaderOf(string|SplFileInfo $file)
    {
        $creader = CReader::fromFile($file);
        $creader->setCPPDirectiveFactory(
            CPPDirectives::factory(
                $this->actionsConfig
            )
        );
        return $creader;
    }

    // ========================================================================

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
    public function process(): void
    {
        $this->actionsConfig->clear();
        $this->actionsConfig['dir.root'] = \getcwd();

        // Init and check phase
        {
            $wd = $this->actionsConfig['pcp.dir'];

            if (!\is_dir($wd))
                \mkdir($wd, recursive: true);
        }

        $this->updatePhase(
            PhaseName::ProcessingFiles,
            PhaseState::Start
        );

        $this->actionsConfig['dateTime'] = $date = new \DateTime();
        $this->actionsConfig['dateTime.format'] = $date->format(\DateTime::ATOM);

        foreach ((array)$this->actionsConfig['pcp.paths'] as $dir)
            $this->processDir($dir, $this->actionsConfig);

        $this->updatePhase(
            PhaseName::ProcessingFiles,
            PhaseState::Stop
        );
    }

    private $newFiles = [];

    private function processDir(\SplFileInfo|string $wdir, Configuration $parentConfig): void
    {
        if (\is_string($wdir))
            $wdir = new \SplFileInfo($wdir);

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

        $it = new \FileSystemIterator($wdir->getPathname());
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

    // ========================================================================

    private function processOneFile(string $fname, Configuration $config): void
    {
        if (\str_ends_with($fname, '.php')) {
            $newFile = \substr($fname, 0, -4);
            $notFile = !\is_file($newFile);

            if ($notFile || IO::olderThan($newFile, $fname))
                \file_put_contents($newFile, IO::get_include_contents($fname));

            // A new file to consider is created
            if ($notFile) {
                $this->newFiles[] = new \SplFileInfo($newFile);
            }
        } elseif (\in_array(\substr($fname, -2), ['.h', '.c']))
            $this->processOneCFile($fname, $config);
    }

    private static function makePCPPragmaConfig(PCPPragma $pcpPragma, Configuration $fileConfig): PCPPragma
    {
        $pcpArguments = $pcpPragma->getArguments();
        $newConfig = self::expandAtConfigArguments($pcpArguments, $fileConfig);

        if ($newConfig !== $pcpArguments)
            $pcpPragma = $pcpPragma->copy($newConfig);

        self::unwrapPrefixedPCPArguments($pcpPragma);
        return $pcpPragma;
    }

    /**
     * Expand any pragma argument qith a key $k of the form $k='@config.#id' with the content of $fileConfig[$k].
     */
    private static function expandAtConfigArguments(Configuration $pcpPragmaArguments, Configuration $fileConfig): Configuration
    {
        $noconfig = $pcpPragmaArguments->isPresent('@noconfig');

        if (!$noconfig) {
            $yesconfig =
                $pcpPragmaArguments->isPresent('@config')
                || $pcpPragmaArguments->isPresent('@config@');

            if (!$yesconfig)
                // Must prepend the general @config
                $argsRawValues = HelpIterables::appends(
                    ['@config' => true],
                    $pcpPragmaArguments->getRawValueIterator()
                );
            else {
                $argsRawValues = $pcpPragmaArguments;
            }
        } else {
            $argsRawValues = $pcpPragmaArguments->toArray(ReadingMode::RawValue);
            unset($argsRawValues['@noconfig']);
        }
        $newArguments = Configurations::emptyTreeCopyOf($pcpPragmaArguments);

        foreach ($argsRawValues as $k => $v) {

            if (!\str_starts_with($k, '@config')) {
                $newArguments[$k] = $v;
                continue;
            }
            $config = $fileConfig->getOptional($k);

            if ($config->isPresent())
                $newArguments->merge($config->get()->getRawValueIterator());
        }
        return $newArguments;
    }

    private static function unwrapPrefixedPCPArguments(PCPPragma $pcpPragma): void
    {
        $cmd = $pcpPragma->getCommand();
        $pcpArguments  = $pcpPragma->getArguments();

        if (!$pcpArguments->nodeIsPresent($cmd))
            return;

        $subTree = $pcpArguments->subTreeView($cmd);

        $pcpArguments->removeNode($cmd);

        foreach ($subTree->getRawValueIterator() as $k => $v)
            $pcpArguments[$k] = $v;
    }

    private function processOneCFile(string $fname, Configuration $fileConfig): void
    {
        $phaseData = ReadingOneFile::fromPath($fname);
        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Start,
            $phaseData
        );

        $creader = $this->creaderOf($fname);
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
                        $element = self::makePCPPragmaConfig($element, $fileConfig);
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
