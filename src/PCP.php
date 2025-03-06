<?php

declare(strict_types=1);

namespace Time2Split\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Config\Entry\ReadingMode;
use Time2Split\Help\IO;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\ActionCommandReader;
use Time2Split\PCP\Action\Actions;
use Time2Split\PCP\Action\IAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PhaseData\ReadingDirectory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\Element\CElement;
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
            'file.extension.pcp' => 'pcp',
            'dir.config-file' => 'config',
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

    public static function creaderOf(string|\SplFileInfo $file, Configuration $pcpCponfig): object
    {
        return App::creaderOfFile($file, (array)$pcpCponfig['pcp.pragma.names']);
    }

    public function getCReaderOf(string|\SplFileInfo $file): object
    {
        return self::creaderOf($file, $this->actionsConfig);
    }

    // ========================================================================

    private ?IAction $monopolyFor = null;

    private function deliverMessage(CElement|ActionCommand $message): array
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

            if ($message instanceof CElement)
                $deliver = $s->onMessage(...);
            else
                $deliver = $s->onCommand(...);

            $moreActions = $deliver($message);

            if (!$moreActions->isEmpty()) {
                $resElements = \array_merge(
                    $resElements,
                    $moreActions->getActions()
                );
            }
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

        foreach ((array)$this->actionsConfig['pcp.paths'] as $dir)
            $this->processDir($dir, $this->actionsConfig);

        $this->updatePhase(
            PhaseName::ProcessingFiles,
            PhaseState::Stop
        );
    }

    private function getDirConfigFiles(\SplFileInfo|string $dir, Configuration $config): array
    {
        $ret = [];
        $pcpfileNames = (array) $config['pcp.dir.config-file'];
        $pcpfileExtension = ((array) $config['pcp.file.extension.pcp'])[0];
        $pcpfileExtension = ".$pcpfileExtension";

        foreach ($pcpfileNames as $pcpfileName) {

            if (!\str_ends_with($pcpfileName, $pcpfileExtension))
                $pcpfileName .= $pcpfileExtension;

            $file = "$dir/$pcpfileName";

            if (\is_file($file))
                $ret[] = $file;
        }
        return $ret;
    }

    private function processOneConfigFile(\SplFileInfo|string $file, Configuration $config): void
    {
        $phaseData = ReadingOneFile::fromPath($file);
        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Start,
            $phaseData
        );

        $creader = ActionCommandReader::ofFile($file);
        try {
            $this->processPCPElements(fn() => $creader->next(), $config);
        } catch (\Exception $e) {
            throw new \Exception("File $file position {$creader->getCursorPosition()}", previous: $e);
        }
        $creader->close();

        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Stop,
            $phaseData
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
        $dirConfig = Configurations::emptyChild($parentConfig);
        $dirConfig['@process.file.dir'] = (string) $wdir;
        $this->setSubscribersConfig($dirConfig);

        // process config dir
        $pcpConfigs = $this->getDirConfigFiles($wdir, $parentConfig);

        foreach ($pcpConfigs as $configFile)
            $this->processOneConfigFile($configFile, $dirConfig);

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
                $this->processOneFile($finfo, $fileConfig);
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

    private function processOneFile(\SplFileInfo $file, Configuration $fileConfig): void
    {
        $fname = $file->getFilename();
        $fpath = $file->getPathname();

        if (\str_ends_with($fname, '.php')) {
            $newFile = \substr($fname, 0, -4);
            $notFile = !\is_file($newFile);

            if ($notFile || IO::olderThan($newFile, $fpath))
                \file_put_contents($newFile, IO::get_include_contents($fpath));

            // A new file to consider is created
            if ($notFile) {
                $this->newFiles[] = new \SplFileInfo($newFile);
            }
        } elseif (\in_array($ext = \substr($fname, -2), ['.h', '.c'])) {
            $fileConfig['@process.file'] = $file->getPathname();
            $fileConfig['@process.file.name'] = $fname;
            $fileConfig['@process.file.suffix'] = $ext;
            $fileConfig['@process.file.baseName'] = \substr($fname, 0, \strlen($fname) - 2);
            $this->processOneCFile($file, $fileConfig);
        }
    }

    private static function makeActionCommand(ActionCommand $element, Configuration $fileConfig, bool $expandAtConfig): ActionCommand
    {
        $arguments = $element->getArguments();
        $command = $element->getName();

        if ($expandAtConfig) {
            $arguments = self::expandAtConfigArguments($arguments, $fileConfig);
            $arguments = self::addTopPublicConfig($arguments, $fileConfig);
        }
        self::unwrapCommandPrefixedArguments($command, $arguments);
        return ActionCommand::create($command, $arguments);
    }

    private static function addTopPublicConfig(Configuration $pcpPragmaArguments, Configuration $fileConfig): Configuration
    {
        return Configurations::hierarchy($fileConfig->copyBranches('@process'), $pcpPragmaArguments);
    }

    /**
     * Expand any pragma argument with a key $k of the form $k='@config.#id' with the content of $fileConfig[$k].
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

    private static function unwrapCommandPrefixedArguments(string $cmd, Configuration $pcpArguments): void
    {
        if (!$pcpArguments->nodeIsPresent($cmd))
            return;

        $subTree = $pcpArguments->subTreeView($cmd);

        $pcpArguments->unsetNode($cmd);

        foreach ($subTree->getRawValueIterator() as $k => $v)
            $pcpArguments[$k] = $v;
    }

    private function processOneCFile(\SplFileInfo|string $file, Configuration $fileConfig): void
    {
        $phaseData = ReadingOneFile::fromPath($file);
        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Start,
            $phaseData
        );

        $creader = $this->getCReaderOf($file);
        try {
            $this->processPCPElements(fn() => $creader->next(), $fileConfig);
        } catch (\Exception $e) {
            throw new \Exception("File $file position {$creader->getCursorPosition()}", previous: $e);
        }
        $creader->close();

        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Stop,
            $phaseData
        );
    }

    private function processPCPElements(callable $next, Configuration $fileConfig): void
    {
        $elements = [];

        while (true) {

            if (!empty($elements))
                $element = \array_pop($elements);
            else {
                $this->updatePhase(PhaseName::ReadingCElement, PhaseState::Start);
                $element = $next();

                if (null === $element)
                    break;
            }

            if ($element instanceof ActionCommand) {
                $expandAtConfig =
                    !isset($this->monopolyFor)
                    || !$this->monopolyFor->noExpandAtConfig();

                $message = self::makeActionCommand($element, $fileConfig, $expandAtConfig);
            } else {
                assert($element instanceof CElement);
                $message = $element;
            }
            $resElements = $this->deliverMessage($message);

            if (!empty($resElements)) {
                // Reverse the order to allow to array_pop($elements) in the original order
                $elements = \array_merge($elements, \array_reverse($resElements));
            }
        }
    }
}
