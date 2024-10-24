<?php

declare(strict_types=1);

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
use Time2Split\PCP\Action\PCP\Generate\Areas;
use Time2Split\PCP\Action\PCP\Generate\InstructionStorage;
use Time2Split\PCP\Action\PCP\Generate\TargetsCode;
use Time2Split\PCP\Action\PCP\Generate\Instruction\Factory;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\CElements;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElementType;
use Time2Split\PCP\C\Element\CPPDirectives;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\PCP\File\Section;
use Time2Split\PCP\File\StreamInsertion;

final class Generate extends BaseAction
{

    private const wd = 'generate';

    private const tmpFile = 'tmp';

    private const DefaultConfig = [
        'generate' => [
            'drop' => null,
            'tags' => null,
            'targets' => '.',
            'targets.prototype' => '${targets}',
            'targets.function' => '${targets}',
            'targets.code' => '${targets}',

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

    public function setConfig($config)
    {
        $this->config = Configurations::hierarchy(
            App::configuration(self::DefaultConfig),
            $config,
            App::emptyConfiguration()
        );
    }

    private static function isPCPGenerate(CElement|CContainer $element, ?string $firstArg = null): bool
    {
        if ($element instanceof CContainer) {

            if (!$element->isPCPPragma())
                return false;

            $element = $element->getCppDirective();
        }
        return CElements::isPCPCommand($element, 'generate', $firstArg);
    }

    private static function PCPIsGenerate(PCPPragma $element, ?string $firstArg = null): bool
    {
        return CElements::PCPIsCommand($element, 'generate', $firstArg);
    }

    // ========================================================================
    private bool $waitingForEnd = false;

    public function hasMonopoly(): bool
    {
        return $this->waitingForEnd;
    }

    public function onMessage(CContainer $ccontainer): array
    {
        if ($ccontainer->isPCPPragma()) {
            $pragma = $ccontainer->getPCPPragma();

            if ($this->PCPIsGenerate($pragma)) {
                $args = $pragma->getArguments();

                if ($this->waitingForEnd) {

                    if (isset($args['end']))
                        $this->waitingForEnd = false;
                } elseif (isset($args['begin']))
                    $this->waitingForEnd = true;
                elseif ($this->instructionWithoutSubject($pragma));
                else
                    $this->doInstruction($pragma);
            }
        } elseif ($ccontainer->isDeclaration()) {
            $this->currentCContainer = $ccontainer;
            $this->processCContainer($ccontainer);
        }
        return [];
    }

    private function makeInstruction(Configuration $instruction): Configuration
    {
        // The order of the $instruction arguments is important
        $first = $instruction;
        $secnd = $this->config->subTreeCopy('generate');
        return Configurations::hierarchy($secnd, $first);
    }

    private function processCContainer(CContainer $ccontainer)
    {
        if ($ccontainer->isDeclaration())
            $this->processCDeclaration($ccontainer->getDeclaration());
    }

    private function processCDeclaration(CDeclaration $declaration)
    {
        if (! $declaration->getElementType()[CElementType::Function])
            return;

        foreach ($this->instructions as $pcpPragma) {
            $i = $this->makeInstruction($pcpPragma->getArguments());
            $this->istorage->put($this->ifactory->create($declaration, $i));
        }
        $this->instructions = [];
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
                    $this->goWorkingDir(self::wd);
                    $this->generate();
                    $this->outWorkingDir();
                }
                break;

            case PhaseName::OpeningDirectory:

                if (PhaseState::Run == $phase->state) {
                    $this->resetConfig();
                }
                break;

            case PhaseName::ReadingOneFile:

                if (PhaseState::Start == $phase->state) {
                    $this->oneFileData = $data;
                    $this->ifactory = new Factory($data);
                    $this->istorage = new InstructionStorage($data);
                    $this->resetConfig();
                    $this->instructions = [];
                } elseif (PhaseState::Stop == $phase->state) {
                    $this->flushFileInfos();
                }
                break;
        }
    }

    private function resetConfig(): void
    {
        $this->config->clear();
    }

    // ========================================================================
    private function doInstruction(PCPPragma $inst): void
    {
        $args = $inst->getArguments();

        if (isset($args['function']) || isset($args['prototype'])) {
            $this->instructions[] = $inst;
        } else {
            // Update the configuration
            $args = Arrays::arrayMapKey(fn($k) => "generate.$k", $args->toArray());
            $this->config->merge($args);
        }
    }

    private function instructionWithoutSubject(PCPPragma $pcpPragma): bool
    {
        try {
            $i = $this->makeInstruction($pcpPragma->getArguments());
            $this->istorage->put($this->ifactory->createWithoutSubject($i));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ========================================================================
    private function flushStorage(): void
    {
        $codes = $this->istorage->getTargetsCode();
        $finfo = $this->oneFileData->fileInfo;
        $filePath = "$finfo.gen.php";

        if ($codes->isEmpty()) {
            // Clean existing files
            if (\is_file($filePath))
                \unlink($filePath);
        } else {
            $path = $finfo->getPath();

            if (\strlen($path) !== 0 && ! \is_dir($path))
                \mkdir($path, recursive: true);

            $export = $codes->array_encode();

            if ($export !== @include $filePath)
                if (false === $i = IO::printPHPFile($filePath, $export))
                    throw new \Exception("Unable to write " . getcwd() . "/$filePath");
        }
    }

    private function flushFileInfos(): void
    {
        $this->goWorkingDir(self::wd);
        $this->flushStorage();
        $this->outWorkingDir();
    }

    // ========================================================================

    private static function includeSource(string $file): TargetsCode
    {
        return TargetsCode::array_decode(include $file);
    }

    private function areaWriter(\SplFileInfo $srcFileInfo, \SplFileInfo $targetFileInfo, array $genCodes)
    {
        $writer = App::fileInsertion((string)$targetFileInfo, self::tmpFile);
        $srcTime = $srcFileInfo->getMTime();
        $srcFile = \substr((string)$srcFileInfo, 1 + \strlen($this->workingDir));

        return new class($writer, $srcTime, $genCodes, $srcFile) {

            private string $srcTimeFormat;

            function __construct(
                private StreamInsertion $writer,
                private int $srcTime,
                private array $genCodes,
                private string $srcFile
            ) {
                $this->srcTimeFormat = \date(DATE_ATOM, $srcTime);

                foreach ($genCodes as $code)
                    $code->moreTags()[] = 'remaining';
            }

            private function makeSrcSectionArguments(): Configuration
            {
                return App::configuration([
                    'src' => $this->srcFile,
                    'mtime' => $this->srcTime - 1
                ]);
            }

            public function write(Area $area): void
            {
                $areaSections = $area->getSections();

                if (empty($areaSections)) {
                    // No section of code has been already written
                    $writeEnd = true;
                    $sectionArgs = $this->makeSrcSectionArguments();
                    $srcSection = Section::createPoint($area->getPCPPragma()->getFileSection()->end);
                    $lastSection = $srcSection;
                } else {
                    $writeEnd = false;
                    $lastSection = \array_pop($areaSections);

                    // If there is a section (begin) then it must be an 'end' section right after
                    assert(! empty($areaSections));

                    foreach ($areaSections as $section) {
                        $sectionArgs = $area->getSectionArguments($section);

                        if ($sectionArgs['src'] === $this->srcFile) {
                            $srcSection = $section;
                            break;
                        }
                    }

                    // Source section not found
                    if (! isset($srcSection)) {
                        $sectionArgs = $this->makeSrcSectionArguments();
                        $srcSection = Section::createPoint($lastSection->begin);
                    }
                }
                $this->writeSection($area, $srcSection, $sectionArgs, $writeEnd);
                $this->writer->seekSet($lastSection->end->pos);
            }

            /**
             * Select the code to write for the area
             */
            private function selectCodeToWrite(Area $area): array
            {
                $areaConfig = Configurations::emptyChild(clone $area->getArguments());
                $selectedCodes = [];

                foreach ($this->genCodes as $code) {
                    $areaConfig->clear();
                    $check = $areaConfig['tags'] ?? true;

                    if (!\is_bool($check)) {

                        if (\is_string($check))
                            $check = [$check];

                        if (\is_array($check)) {
                            // All the tags of '$check' must be a part of the instruction tags
                            $tags = $code->getTags();
                            $checks = $check;
                            $check = true;

                            foreach ($checks as $checkTheTag) {

                                if (!\in_array(\strtolower($checkTheTag), $tags)) {
                                    $check = false;
                                    break;
                                }
                            }
                        } else {
                            // Set tags for interpolation
                            foreach ($code->getTags() as $tag)
                                $areaConfig[$tag] = true;

                            // Interpolate
                            $check = $areaConfig['tags'];
                        }
                    }

                    if ($check) {
                        // Remove 'remaining' tag
                        $code->moreTags()->exchangeArray([]);
                        $selectedCodes[] = $code;
                    }
                }
                return $selectedCodes;
            }

            private function writeSection(Area $area, Section $section, Configuration $sectionArguments, bool $writeEnd): void
            {
                $sectionMTime = (int) $sectionArguments['mtime'];

                // No need to write
                if ($sectionMTime >= $this->srcTime)
                    $writer = null;
                else
                    $writer = $this->writer;

                $selectedCodes = $this->selectCodeToWrite($area);

                if (isset($writer)) {
                    $writer->seekSet($section->begin->pos);
                    $writer->seekSkip($section->end->pos);

                    // Must add a eol char
                    if ($section->begin->line === $section->end->line)
                        $writer->write("\n");

                    if (! empty($selectedCodes)) {
                        $writer->write("#pragma pcp generate begin mtime=$this->srcTime src=\"$this->srcFile\"\n// $this->srcTimeFormat\n");
                        foreach ($selectedCodes as $code)
                            $writer->write("{$code->getText()}\n");
                        if ($writeEnd)
                            $this->writer->write("#pragma pcp generate end\n");
                    }
                }
            }

            public function noInsertion(): bool
            {
                return $this->writer->insertionCount() === 0;
            }

            public function close()
            {
                $this->writer->close();
            }
        };
    }

    private string $workingDir;

    private function generate(): void
    {
        $sourcesPath = $this->config['dir.root'];
        $this->workingDir = $this->config['pcp.dir'];

        $sourceCache = [];

        $dirIterator = new \RecursiveDirectoryIterator('.', \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME);
        $dirIterator = new \RecursiveIteratorIterator($dirIterator);
        $dirIterator = new \RegexIterator($dirIterator, "/^.+\.gen\.php$/");

        $maxSrcMTime = 0;

        foreach ($dirIterator as $genFilePath) {
            // Skip the './' prefix
            $genFilePath = \substr((string)$genFilePath, 2);

            $srcFile = \substr($genFilePath, 0, -8);
            $srcFilePath = "$sourcesPath/$srcFile";

            // The source file has been deleted
            if (! \is_file($srcFilePath)) {
                \unlink($genFilePath);
                continue;
            }
            $baseFileInfo = new \SplFileInfo($srcFilePath);
            $srcMTime = $baseFileInfo->getMTime();
            $targetsCode = $sourceCache[$genFilePath] ??= self::includeSource($genFilePath);

            foreach ($targetsCode as $target => $genCodes) {
                $targetFilePath = (string)$target->getFileInfo();

                // The target file has been deleted
                if (! \is_file($targetFilePath))
                    continue;

                $areas = $this->nextArea($targetFilePath, $srcFile, $srcMTime);
                // Must read the file before its writing
                $areas = \iterator_to_array($areas);

                $targetFileInfo = new \SplFileInfo($targetFilePath);
                $targetLastMTime = $targetFileInfo->getMTime();
                $areaWriter = $this->areaWriter($baseFileInfo, $targetFileInfo, $genCodes);

                foreach ($areas as $area)
                    $areaWriter->write($area);

                $areaWriter->close();

                $maxSrcMTime = \max($maxSrcMTime, $srcMTime);

                // Reset the target mtime: that permits to detect a worthless section generation
                if ($areaWriter->noInsertion())
                    \touch($targetFilePath, $targetLastMTime);
                elseif ($targetLastMTime != $maxSrcMTime)
                    \touch($targetFilePath, $maxSrcMTime);

                \clearstatcache(filename: $targetFilePath);
            }
        }
    }

    private function nextArea(string $targetFilePath, string $srcFile, int $srcMTime): \Iterator
    {
        $creader = CReader::fromFile($targetFilePath);
        $creader->setCPPDirectiveFactory(CPPDirectives::factory($this->config));
        $next = null;

        while (true) {

            if (isset($next)) {
                $area = $next;
                $next = null;
            } else
                $area = $creader->next();

            if (! isset($area))
                break;
            if (! Generate::isPCPGenerate($area, 'area'))
                continue;

            /**
             * @var \Time2Split\PCP\C\CPPDirective $area
             */

            $arguments = App::configShift($area->getArguments());
            $sectionsArguments = new \SplObjectStorage();

            $cppElement = $creader->next();
            $sections = [];

            if (! isset($cppElement));
            else if (self::isPCPGenerate($cppElement, 'begin')) {
                /**
                 * @var \Time2Split\PCP\C\CPPDirective $cppElement
                 */

                while (true) {
                    $end = $creader->next();

                    if (! isset($end))
                        break;

                    $isPCPEnd = self::isPCPGenerate($end, 'end');

                    if ($isPCPEnd || self::isPCPGenerate($end, 'begin')) {
                        /**
                         * @var \Time2Split\PCP\C\CPPDirective $end
                         */
                        $section = new Section($cppElement->getFileSection()->begin, $end->getFileSection()->begin);
                        $sectionsArguments->attach($section, $cppElement->getArguments());
                        $sections[] = $section;
                        $cppElement = $end;
                    }
                    if ($isPCPEnd)
                        break;
                }

                if (! isset($isPCPEnd))
                    throw new \Exception("$targetFilePath: waiting 'end' pcp pragma from $cppElement; reached the end of the file");

                /**
                 * @var \Time2Split\PCP\C\CPPDirective $end
                 */
                if (isset($end))
                    $sections[] = $end->getFileSection();
            } else
                $next = $cppElement;

            yield Areas::create($area, $arguments, $sectionsArguments, ...$sections);
        }
        $creader->close();
    }
}
