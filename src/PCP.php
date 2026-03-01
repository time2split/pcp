<?php

declare(strict_types=1);

namespace Time2Split\PCP;

use FilesystemIterator;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Config\Entry\ReadingMode;
use Time2Split\Help\IO;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\ActionCommandReader;
use Time2Split\PCP\Action\IAction;
use Time2Split\PCP\Action\InvalidActionCommandException;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseData\ProcessingFiles;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PhaseData\ReadingDirectory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\PCPReader;
use Time2Split\PCP\DataFlow\BasePublisher;
use Time2Split\PCP\File\CursorPosition;
use Time2Split\PCP\File\FileCursor;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\FS\FSFile;
use Time2Split\PCP\FS\FSPath;
use Time2Split\PCP\FS\FSPathException;
use Time2Split\PCP\Help\HelpIterables;

/**
 * PHP: C preprocessor
 *
 * @author zuri
 * @date 02/07/2022 12:36:56 CEST
 */
class PCP extends BasePublisher
{
    // ===============
    // EXTERNAL CONFIG
    // ===============

    /**
     * Working directory
     */
    const CONFIG_FS_WD = 'pcp.fs.wd';

    const CONFIG_FS_ROOT = 'pcp.fs.root';

    const CONFIG_SOURCE_PATHS = 'pcp.fs.paths';

    // ===============
    // INTERNAL CONFIG
    // ===============

    /**
     * PCP main action (process, clean)
     */
    const CONFIG_PCP_ACTION = '@pcp.action';

    const CONFIG_PCP_C_PRAGMA_NAME = '@pcp.pragma';

    const CONFIG_PCP_FILE_EXTENSION = '@pcp.file.extension';

    // ========================================================================

    private const array DefaultConfig = [

        // External
        self::CONFIG_FS_WD => 'pcp.wd',
        self::CONFIG_SOURCE_PATHS => ['src'],

        // Internal
        self::CONFIG_FS_ROOT => '.',
        self::CONFIG_PCP_FILE_EXTENSION => 'pcp',
        self::CONFIG_PCP_C_PRAGMA_NAME => 'pcp',
    ];

    private FSDir $fsSrcRoot;

    private FSDir $fsWDRoot;

    private Configuration $actionsConfig;

    public function __construct(Configuration $appConfig, array $actions)
    {
        parent::__construct();

        if (empty($actions))
            throw new \Exception("No action specified");

        $this->actionsConfig = self::makeActionsConfig($appConfig);
        self::subscribeActions(...$actions);

        $cwd = FS::dirOf(\getcwd());
        $this->fsSrcRoot = self::setConfigFSDir(
            $this->actionsConfig,
            PCP::CONFIG_FS_ROOT,
            $cwd,
        );
        $this->fsWDRoot = self::setConfigFSDir(
            $this->actionsConfig,
            PCP::CONFIG_FS_WD,
            $cwd,
        );
    }

    private function subscribeActions(IAction ...$actions): void
    {
        assert(isset($this->actionsConfig));
        \array_walk($actions, $this->subscribe(...));
        $this->setSubscribersConfig($this->actionsConfig);
    }

    private static function makeActionsConfig(Configuration $appConfig): Configuration
    {
        return Configurations::hierarchy(
            App::emptyConfiguration()->mergeTree(self::DefaultConfig),
            $appConfig,
            App::emptyConfiguration()
        );
    }

    public static function creaderOf(FSFile $fsFile, Configuration $pcpCponfig): PCPReader
    {
        return App::creaderOfFile($fsFile, (array)$pcpCponfig[PCP::CONFIG_PCP_C_PRAGMA_NAME]);
    }

    public function getCReaderOf(FSFile $fsFile): PCPReader
    {
        return self::creaderOf($fsFile, $this->actionsConfig);
    }

    // ========================================================================
    // ACTION COMMANDS

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

    // ========================================================================
    // CONFIG

    private static function _configAddProcessDirInfos(FSDir $fsDir, Configuration $dirConfig): void
    {
        $dirConfig['@process.file.dir'] = (string) $fsDir->canonical();
    }

    private static function _configProcess_getFileNameAndExtensionAndBaseName(FSFile $fsFile): array
    {
        $file = $fsFile->info();
        $fname = $file->getFilename();
        $ext = \substr($fname, -2);
        $basename = \substr($fname, 0, \strlen($fname) - 2);
        // assert(\in_array($ext, ['.h', '.c'])); // .pcp is allowed
        return [$fname, $ext, $basename];
    }

    private static function _configAddProcessFileInfos(FSFile $fsFile, Configuration $fileConfig): void
    {
        [$fname, $ext, $basename] = self::_configProcess_getFileNameAndExtensionAndBaseName($fsFile);
        $fileConfig['@process.file'] = (string)$fsFile;
        $fileConfig['@process.file.name'] = $fname;
        $fileConfig['@process.file.suffix'] = $ext;
        $fileConfig['@process.file.baseName'] = $basename;
    }

    public static function getProcessFileInfos(FSFile $fsFile): array
    {
        [$fname, $ext, $basename] = self::_configProcess_getFileNameAndExtensionAndBaseName($fsFile);
        return [
            '@process.file.dir' => (string)$fsFile->getPath()->canonical(),
            '@process.file' => (string)$fsFile,
            '@process.file.name' => $fname,
            '@process.file.suffix' => $ext,
            '@process.file.baseName' => $basename,
        ];
    }

    // ========================================================================
    // FILESYSTEM

    private static function setConfigFSDir(
        Configuration $config,
        string $offset,
        ?FSDir $relativeRoot = null,
    ): FSDir {
        $strpath = $config[$offset];

        if (!\is_string($strpath))
            throw new \InvalidArgumentException("($offset: $strpath) Must be a string");

        return  self::resolveFSPath(FS::dirOf($strpath), $relativeRoot);
    }

    /**
     * Resolves a relative or rooted path.
     * 
     * @throws \InvalidArgumentException
     *      If path is relative but gets above its parent
     *      (ie. it cannot be canonicalized).
     */
    public static function resolveFSPath(
        FSPath $fspath,
        ?FSDir $relativeRoot = null,
    ): FSPath {
        $root =
            FS::rootedOrSubPath(
                $a = $relativeRoot ?? FS::dirOf(\getcwd()),
                $b = $fspath,
            );

        if (!$root->isPresent())
            throw self::subPathError($a, $b);

        return $root->get()->canonical();
    }

    /**
     * Resolves a relative or rooted path.
     * 
     * @throws \InvalidArgumentException
     *      If path is relative but gets above its parent
     *      (ie. it cannot be canonicalized).
     */
    private static function resolveSubPath(
        FSDir $root,
        FSPath $fsPath,
    ): FSPath {
        $subPath = FS::subPathOf($root, $fsPath);

        if (!$subPath->isPresent())
            throw self::subPathError($root, $fsPath);

        return $subPath->get();
    }

    public static function resolveRelativePath(
        FSPath $fsPath,
    ): FSPath {
        $relPath = FS::relativePath($fsPath);

        if ($relPath->isEmpty())
            throw self::subPathError($fsPath, FS::dirOf(\getcwd()));

        return $relPath->get();
    }

    private static function subPathError(FSDir $root, FSPath $fs): \Exception
    {
        $canon = $fs->canonical();
        return new FSPathException(
            <<<EOD
            The path cannot be canonicalized ($canon).
             - The path cannot get outside its parent directory $root.
            
            EOD,
            fsPath: $fs,
        );
    }

    // ========================================================================
    // PROCESSING

    /**
     * Process the files inside the current working directory.
     */
    public function process(): void
    {
        $this->actionsConfig->clear();
        IO::wdPush("$this->fsSrcRoot");

        // Init and check phase
        {
            if (!$this->fsWDRoot->inFS())
                \mkdir(
                    (string)$this->fsWDRoot,
                    recursive: true
                );
        }
        $processingFiles = new ProcessingFiles(
            $this->fsSrcRoot,
            $this->fsWDRoot
        );
        try {
            $this->updatePhase(
                PhaseName::ProcessingFiles,
                PhaseState::Start,
                $processingFiles
            );
            $fsDirs = [];

            foreach ((array)$this->actionsConfig[PCP::CONFIG_SOURCE_PATHS] as $dir) {
                $fsDir = self::resolveRelativePath(FS::dirOf($dir));
                $fsDirs[] = $fsDir;
            }

            foreach ($fsDirs as $fs) {
                $this->processDir($fs, $this->actionsConfig);
            }

            $this->updatePhase(
                PhaseName::ProcessingFiles,
                PhaseState::Stop,
                $processingFiles
            );
        } catch (\Exception $e) {
            throw $e;
        } finally {
            IO::wdPop();
        }
    }

    // ========================================================================

    private function processDirPCPFile(FSDir $fsDir, Configuration $dirConfig): void
    {
        $fsPCPFile = $fsDir->appendFile('.' . $dirConfig[PCP::CONFIG_PCP_FILE_EXTENSION]);

        if (!$fsPCPFile->inFS()) {
            return;
        }
        $this->processOneConfigFile($fsPCPFile, $dirConfig);
    }

    private function processFilePCPFile(FSFile $fsFile, Configuration $fileConfig): void
    {
        $fsPCPFile = $fsFile->setFile($fsFile->info()->getFilename() . '.' . $fileConfig[PCP::CONFIG_PCP_FILE_EXTENSION]);

        if (!$fsPCPFile->inFS()) {
            return;
        }
        $this->processOneConfigFile($fsPCPFile, $fileConfig);
    }

    private function processOneConfigFile(FSFile $fsFile, Configuration $config): void
    {
        $phaseData = ReadingOneFile::fromPath($fsFile);
        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Start,
            $phaseData
        );

        $creader = ActionCommandReader::ofFile($fsFile);
        try {
            $this->processPCPElements($creader, $fsFile, $config);
        } catch (PCPException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Exception("File $fsFile position {$creader->getCursorPosition()}", previous: $e);
        }
        $creader->close();

        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Stop,
            $phaseData
        );
    }

    // ========================================================================
    // DIR PROCESSING

    private $newFiles = [];

    private function processDir(FSDir $fsDir, Configuration $parentConfig): void
    {
        $phaseData = ReadingDirectory::fromPath($fsDir);

        $this->updatePhase(
            PhaseName::OpeningDirectory,
            PhaseState::Start,
            $phaseData
        );
        $dirConfig = Configurations::emptyChild($parentConfig);
        self::_configAddProcessDirInfos($fsDir, $dirConfig);
        $this->setSubscribersConfig($dirConfig);

        // process config dir
        $this->processDirPCPFile($fsDir, $dirConfig);

        $this->updatePhase(
            PhaseName::OpeningDirectory,
            PhaseState::Run,
            $phaseData
        );

        $it = new \FileSystemIterator(
            "./$fsDir",
            FilesystemIterator::CURRENT_AS_PATHNAME
                | FilesystemIterator::SKIP_DOTS
        );
        $dirs = [];

        $fileConfig = Configurations::emptyChild($dirConfig);
        $this->setSubscribersConfig($fileConfig);

        while (true) {

            foreach ($it as $path) {
                // Remove './' prefix
                $path = \substr($path, 2);

                if (\is_dir($path)) {
                    $dirs[] = FS::dirOf($path);
                } else {
                    $fileConfig->clear();
                    $this->processOneFile(FS::fileOf($path), $fileConfig);
                }
            }

            if (0 === \count($this->newFiles))
                break;

            // Iterate through new files
            $it = $this->newFiles;
            $this->newFiles = [];
        }

        foreach ($dirs as $fsSubDir)
            $this->processDir($fsSubDir, $dirConfig);

        $this->updatePhase(
            PhaseName::OpeningDirectory,
            PhaseState::Stop,
            $phaseData
        );
    }

    // ========================================================================
    // C FILE PROCESSING

    private function processOneFile(FSFile $fsFile, Configuration $fileConfig): void
    {
        $file = $fsFile->info();
        $fname = $file->getFilename();
        $fpath = $file->getPathname();

        if (\str_ends_with($fname, '.php')) {
            $newFile = \substr($fname, 0, -4);
            $notFile = !\is_file($newFile);

            if ($notFile || IO::olderThan($newFile, $fpath))
                \file_put_contents($newFile, IO::get_include_contents($fpath));

            // A new file to consider is created
            if ($notFile) {
                //TODO: check good key -> val
                $this->newFiles[$newFile] = $newFile;
            }
        } elseif (\in_array(\substr($fname, -2), ['.h', '.c'])) {
            self::_configAddProcessFileInfos($fsFile, $fileConfig);
            $this->processOneCFile($fsFile, $fileConfig);
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

    private function processOneCFile(FSFile $fsFile, Configuration $fileConfig): void
    {
        $phaseData = ReadingOneFile::fromPath($fsFile);
        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Start,
            $phaseData
        );
        // Important to be after the phase start to have the good behaviour of the actions (eg. for action)
        $this->processFilePCPFile($fsFile, $fileConfig);

        $creader = $this->getCReaderOf($fsFile);
        try {
            $this->processPCPElements($creader, $fsFile, $fileConfig);
        } catch (PCPException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Exception("File $fsFile position {$creader->getCursorPosition()}", previous: $e);
        }
        $creader->close();

        $this->updatePhase(
            PhaseName::ReadingOneFile,
            PhaseState::Stop,
            $phaseData
        );
    }

    private function processPCPElements(
        PCPReader $creader,
        FSFile $fsFile,
        Configuration $fileConfig
    ): void {
        $elements = [];
        $lastFileCursor = new FileCursor($fsFile, CursorPosition::zero());

        while (true) {

            if (!empty($elements))
                $element = \array_pop($elements);
            else {
                $this->updatePhase(
                    PhaseName::ReadingCElement,
                    PhaseState::Start
                );
                $element = $creader->next();

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
            try {
                $resElements = $this->deliverMessage($message);
            } catch (InvalidActionCommandException $e) {
                throw $e->setFileCursor($lastFileCursor);
            }
            $lastFileCursor = $lastFileCursor->setCursorPosition($creader->getCursorPosition());

            if (!empty($resElements)) {
                // Reverse the order to allow to array_pop($elements) in the original order
                $elements = \array_merge($elements, \array_reverse($resElements));
            }
        }
    }
}
