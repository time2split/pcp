<?php
namespace Time2Split\PCP\Action\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\Arrays;
use Time2Split\Help\IO;
use Time2Split\PCP\App;
use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PCP\Generate\Area;
use Time2Split\PCP\Action\PCP\Generate\InstructionStorage;
use Time2Split\PCP\Action\PCP\Generate\TargetStorage;
use Time2Split\PCP\Action\PCP\Generate\TargetsCode;
use Time2Split\PCP\Action\PCP\Generate\Instruction\Factory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\CDeclarationGroup;
use Time2Split\PCP\C\CDeclarationType;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\CElements;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CPPDirectives;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\PCP\File\StreamInsertion;

final class Generate extends BaseAction
{

    private const wd = 'generate';

    private const DefaultConfig = [
        'generate' => [
            'tags' => null,
            'targets' => '.',
            'targets.prototype' => '${targets}',

            'name.base' => null,
            'name.prefix' => null,
            'name.suffix' => null,
            'name.format' => '${name.prefix}${name.base}${name.suffix}'
        ]
    ];

    private Factory $ifactory;

    private InstructionStorage $istorage;

    private ?CContainer $currentCContainer = null;

    private array $instructions;

    private ReadingOneFile $oneFileData;

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        $this->config = Configurations::emptyChild($this->config);
    }

    public static function isPCPGenerate(CElement $element, ?string $firstArg = null): bool
    {
        return CElements::isPCPCommand($element, 'generate') && (! isset($firstArg) || $firstArg === App::configFirstKey($element->getArguments()));
    }

    // ========================================================================
    public function onMessage(CContainer $ccontainer): array
    {
        if ($ccontainer->isPCPPragma()) {
            $pragma = $ccontainer->getPCPPragma();

            if ($pragma->getCommand() === 'generate') {
                $this->doInstruction($pragma);
            }
        } elseif ($ccontainer->isDeclaration()) {
            $this->currentCContainer = $ccontainer;
            $this->processCContainer($ccontainer);
        }
        return [];
    }

    private function processCContainer(CContainer $ccontainer)
    {
        if ($ccontainer->isDeclaration())
            $this->processCDeclaration($ccontainer->getDeclaration());
    }

    private function processCDeclaration(CDeclaration $declaration)
    {
        if (! $declaration->getType() === CDeclarationType::tfunction)
            return;

        if ( //
        $declaration->getGroup() === CDeclarationGroup::definition || //
        ($declaration->getGroup() === CDeclarationGroup::declaration && //
        $declaration->getType() === CDeclarationType::tfunction)) {

            foreach ($this->instructions as $instruction) {
                // The order of the $instruction arguments is important
                $first = $instruction->getArguments();
                $secnd = $this->config->subConfig('generate');

                $i = Configurations::hierarchy($secnd, $first);
                $i = $this->decorateConfig($i);

                $this->istorage->put($this->ifactory->create($declaration, $i));
            }
            $this->instructions = [];
        }
    }

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::ReadingCElement:

                if (PhaseState::Start == $phase->state) {

                    if ($this->currentCContainer) {
                        $this->processCContainer($this->currentCContainer);
                        $this->currentCContainer = null;
                    }
                }
                break;

            case PhaseName::ProcessingFiles:

                if (PhaseState::Start == $phase->state) {
                    $this->goWorkingDir();

                    if (! \is_dir(self::wd))
                        mkdir(self::wd);

                    $this->outWorkingDir();
                } elseif (PhaseState::Stop == $phase->state) {
                    $this->generate();
                }
                break;

            case PhaseName::ReadingOneFile:

                if (PhaseState::Start == $phase->state) {
                    $this->oneFileData = $data;
                    $this->oneFileMTime = $data->fileInfo->getMTime();
                    $this->ifactory = new Factory($data);
                    $this->istorage = new InstructionStorage($data);

                    $this->instructions = [];

                    // Reset the config for the file
                    $this->config->clear();
                    Configurations::mergeArrayRecursive($this->config, self::DefaultConfig);
                } elseif (PhaseState::Stop == $phase->state) {
                    $this->flushFileInfos();
                }
                break;
        }
    }

    // ========================================================================
    private function doInstruction(PCPPragma $inst): void
    {
        $args = $inst->getArguments();

        if (isset($args['function']) || isset($args['prototype'])) {
            $this->instructions[] = $inst;
        } else {
            // Update the configuration
            $args = Arrays::map_key(fn ($k) => "generate.$k", $args->toArray());
            Configurations::mergeTraversable($this->config, $args);
        }
    }

    // ========================================================================
    private function flushStorage(): void
    {
        $codes = $this->istorage->getTargetsCode();
        $finfo = $this->oneFileData->fileInfo;
        $fileDir = "{$finfo->getPathInfo()}/";

        if (! \is_dir($fileDir))
            \mkdir($fileDir, 0777, true);

        $filePath = "$fileDir/{$finfo->getFileName()}.gen.php";

        if ($codes->isEmpty()) {
            // Clean existing files
            if (\is_file($filePath))
                \unlink($filePath);
        } else {
            $export = $codes->array_encode();

            if ($export !== @include $filePath)
                IO::printPHPFile($filePath, $export);
        }
    }

    private function flushFileInfos(): void
    {
        $this->goWorkingDir(self::wd);
        $this->flushStorage();
        $this->outWorkingDir();
    }

    // ========================================================================
    private function skipGenerated($stream): int
    {
        $pos = \ftell($stream);
        $reader = CReader::fromStream($stream, false);
        $reader->setCPPDirectiveFactory(CPPDirectives::factory($this->config));

        $cppDirective = $reader->nextCPPDirective();

        if (! isset($cppDirective) || ! CContainer::of($cppDirective)->isPCPPragma() || $cppDirective->getCommand() !== 'begin')
            goto noBegin;

        while (true) {
            $cppDirective = $reader->nextCPPDirective();

            if (isset($cppDirective) && CContainer::of($cppDirective)->isPCPPragma() && $cppDirective->getCommand() === 'end')
                break;
        }
        return \ftell($stream) - $pos;
        noBegin:
        $reader->close();
        return 0;
    }

    private static function includeSource(string $file): TargetsCode
    {
        return TargetsCode::array_decode(include $file);
    }

    private function areaWriter(StreamInsertion $writer, int $genTime, array $genCodes)
    {
        return new class($writer, $genTime, $genCodes) {

            function __construct(private StreamInsertion $writer, private int $genTime, private array $genCodes)
            {
                foreach ($genCodes as $code)
                    $code->moreTags()[] = 'remaining';
            }

            public function write(Area $area): void
            {
                $writer = $this->writer;
                $genTime = $this->genTime;
                $areaMTime = (int) $area->getArguments()['mtime'];

                $cursors = $area->getFileCursors();

                if ($areaMTime >= $genTime) {
                    $writer->seekSet($cursors[1]->getPos());
                } else {
                    $writer->seekSet($cursors[0]->getPos());
                    $writer->seekSkip($cursors[1]->getPos());

                    $date = \date(DATE_ATOM, $genTime);
                    $writer->write("#pragma pcp begin mtime=$genTime date=\"$date\"\n");
                    $config = Configurations::emptyChild(clone $area->getArguments());

                    foreach ($this->genCodes as $code) {
                        $config->clear();
                        $check = $config['tags'];

                        if (\is_string($check)) {
                            $check = \in_array($check, $code->getTags());
                        } else {

                            // Set tags for interpolation
                            foreach ($code->getTags() as $tag)
                                $config[$tag] = true;

                            // Interpolate
                            $check = $config['tags'];
                        }

                        if ($check) {
                            $code->moreTags()->exchangeArray([]);
                            $writer->write("{$code->getText()}\n");
                        }
                    }
                    $writer->write("#pragma pcp end\n");
                }
            }

            public function close()
            {
                $this->writer->close();
            }
        };
    }

    private function generate(): void
    {
        $sourcesPath = \getcwd();
        $this->goWorkingDir(self::wd);

        // $cppNames = (array) $this->config['pcp.name'];
        // $cppName = Arrays::first($cppNames);
        $sourceCache = [];

        $dirIterator = new \RecursiveDirectoryIterator('.', \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME);
        $dirIterator = new \RecursiveIteratorIterator($dirIterator);
        $dirIterator = new \RegexIterator($dirIterator, "/^.+\.gen\.php$/");

        foreach ($dirIterator as $genFilePath) {
            // Skip the './' prefix
            $genFilePath = \substr($genFilePath, 2);

            $baseFilePath = \substr($genFilePath, 0, - 8);
            $baseFilePath = "$sourcesPath/$baseFilePath";
            $srcMTime = \filemtime($baseFilePath);
            $targetsCode = $sourceCache[$genFilePath] ??= self::includeSource($genFilePath);

            foreach ($targetsCode as $target => $genCodes) {
                $targetFilePath = "$sourcesPath/{$target->getFileInfo()}";

                if (! \is_file($targetFilePath))
                    continue;

                $targetFileInfo = new \SplFileInfo($targetFilePath);
                $creader = CReader::fromFile($targetFileInfo);
                $creader->setCPPDirectiveFactory(CPPDirectives::factory($this->config));
                $areas = TargetStorage::areasIterator($targetFileInfo, $srcMTime, $creader);
                $areas = \iterator_to_array($areas);
                $creader->close();

                $targetLastMTime = \filemtime($targetFilePath);

                $writer = App::fileInsertion($targetFilePath, 'tmp');
                $areaWriter = $this->areaWriter($writer, $srcMTime, $genCodes);

                foreach ($areas as $area)
                    $areaWriter->write($area);

                $areaWriter->close();

                // No modification: reset the mtime
                if ($writer->insertionCount() === 0)
                    \touch($targetFilePath, $targetLastMTime);
            }
        }
        $this->outWorkingDir();
        return;
        unset($sourceCache);
    }
}