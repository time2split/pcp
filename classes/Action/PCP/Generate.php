<?php
namespace Action\PCP;

enum SourceType: string
{

    case Prototype = 'from.prototype';

    case Function = 'from.function';

    case Macro = 'from.macro';
}

class Generate extends \Action\BaseAction
{

    private const wd = 'generate';

    private const DefaultConfig = [
        'target' => '.',
        'prefix' => '',
        'always.prototype' => false,
        'always.function' => false
    ];

    private array $storage;

    private array $area;

    private \Data\TreeConfig $myConf;

    private int $groupId = 0;

    private ?\C\Macro $nextInstruction;

    private \Action\PhaseData\ReadingOneFile $fileInfo;

    public function __construct(\Data\TreeConfig $config)
    {
        parent::__construct($config);
        $this->storage = [];
    }

    public function onMessage(\Action\IActionMessage $msg): void
    {
        if ($msg instanceof \C\Macro) {

            if ($msg->getDirective() === 'pragma' && $msg->getCommand() === 'generate')
                $this->doInstruction($msg);
            elseif ($msg->getDirective() === 'define') {
                $instruction = $this->nextMacroInstruction($msg);

                if (null !== $instruction) {
                    $iargs = $instruction->getArguments();
                    $this->storage[] = [
                        $iargs + $this->storeSelectConf(),
                        $msg
                    ];
                }
            }
        } elseif ($msg instanceof \C\Declaration) {

            switch ($msg->getType()) {

                case \C\DeclarationType::tfunction:
                    $instruction = $this->nextInstruction($msg);

                    if ( //
                    $msg->getGroup() === \C\DeclarationGroup::definition || //
                    $msg->getGroup() === \C\DeclarationGroup::declaration && $msg->getType() === \C\DeclarationType::tfunction) {
                        $this->storeGroup($msg, $instruction);
                        unset($this->myConf['function']);
                    }
                    break;
            }
        }
    }

    public function onPhase(\Action\Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case \Action\PhaseName::ProcessingFiles:

                if (\Action\PhaseState::Start == $phase->state) {
                    $this->goWorkingDir();

                    if (! \is_dir(self::wd))
                        mkdir(self::wd);

                    $this->outWorkingDir();
                } elseif (\Action\PhaseState::Stop == $phase->state) {
                    $this->flushWD();
                    $this->generate();
                }
                break;

            case \Action\PhaseName::ReadingOneFile:

                if (\Action\PhaseState::Start == $phase->state) {
                    $this->oneFileData = $data;

                    // Reset the config for the file
                    $this->myConf = $this->config->child();
                    $this->myConf->arrayMergeRecursive(self::DefaultConfig);
                } elseif (\Action\PhaseState::Stop == $phase->state) {
                    $this->flushFileInfos();
                }
                break;
        }
    }

    // ========================================================================
    private function nextMacroInstruction(\C\Macro $macro): ?\C\Macro
    {
        if (isset($this->nextInstruction)) {
            $next = $this->nextInstruction;
            $this->nextInstruction = null;
            return $next;
        }
        return null;
    }

    private function nextInstruction(\C\Declaration $decl): \C\Macro
    {
        if (! isset($this->nextInstruction)) {

            // Function definition
            if ($decl->getType() === \C\DeclarationType::tfunction && $decl->getGroup() === \C\DeclarationGroup::definition) {

                if (($p = isset($this->myConfig['always.prototype'])) && isset($this->myConfig['always.function']));
                elseif ($p)
                    $next = \C\Macro::fromText('generate prototype');
                else
                    $next = \C\Macro::fromText('generate function');
            }
        } else {
            $next = $this->nextInstruction;
            $this->nextInstruction = null;
        }

        if (! isset($next))
            throw new \Exception("generate: Unable to set the next instruction");

        return $next;
    }

    private function doInstruction(\C\Macro $inst): void
    {
        $args = $inst->getArguments();

        if (isset($args['area'])) {
            $this->area[] = [
                'tags' => $args['tag'] ?? null,
                'pos' => $inst->getFileCursors()[1]->getPos(),
                'date' => $this->config['dateTime']
            ];
        } elseif (isset($args['function']) || isset($args['prototype'])) {

            if (isset($this->nextInstruction))
                throw new \Exception("Cannot execute the instruction '$inst' because another one is already waiting '$this->nextInstruction'");

            $this->nextInstruction = $inst;
        } else
            $this->myConf->arrayMerge($args);
    }

    // ========================================================================
    private function prototypeToString(array $declaration): string
    {
        $ret = '';
        $e = $declaration;
        $lastIsAlpha = false;

        foreach ($e['items'] as $s) {
            $len = \strlen($s);

            if ($len == 0)
                continue;

            if ($lastIsAlpha && ! \ctype_punct($s)) {
                $ret .= " $s";
            } else {
                $lastIsAlpha = $len > 0 ? \ctype_alpha($s[$len - 1]) : false;
                $ret .= $s;
            }
        }

        if ($declaration['type'] == \C\DeclarationType::tfunction) {
            $params = $e['parameters'] ?? null;

            $ret .= "(";

            if (! empty($params)) {
                $active = false;

                $ret .= $this->prototypeToString(\array_shift($params));

                foreach ($params as $p) {
                    $ret .= ', ';
                    $ret .= $this->prototypeToString(\array_shift($params));
                }
            }
            $ret .= ")";
        }
        return $ret;
    }

    // ========================================================================
    private function getTargets(array $targets): array
    {
        $ret = [];

        foreach ($targets as $t) {

            if ($t === '.')
                $t = (string) $this->oneFileData->fileInfo;
            elseif (false === \strpos('/', $t) || \str_starts_with('./', $t))
                $t = "{$this->oneFileData->fileInfo->getPathInfo()}/$t";

            $ret[] = $t;
        }
        return $ret;
    }

    private static function getTargetKey(array $targets): string
    {
        \sort($targets);
        return \implode('//', $targets);
    }

    // ========================================================================
    private const storageElements = [
        'function',
        'prototype',
        'macro'
    ];

    private function storeSelectConf(): array
    {
        return \Help\Arrays::subSelect($this->myConf->toArray(), [
            'target',
            'prefix'
        ]);
    }

    private function storeGroup(\C\Declaration $decl, \C\Macro $instruction): void
    {
        $iargs = $instruction->getArguments();
        $this->storage[] = [
            $iargs + $this->storeSelectConf(),
            $decl
        ];
    }

    private function flushWD(): void
    {
        $this->goWorkingDir(self::wd);
        $this->outWorkingDir();
    }

    private function flushFileInfos(): void
    {
        $this->goWorkingDir(self::wd);
        $this->flushStorage();
        $this->flushArea();
        $this->outWorkingDir();
    }

    private function flushArea(): void
    {
        $areas = \array_filter((array) $this->area);

        if (empty($areas))
            return;

        $finfo = $this->oneFileData->fileInfo;
        $fileDir = "{$finfo->getPathInfo()}/";

        if (! is_dir($fileDir))
            mkdir($fileDir, 0777, true);

        \Help\IO::printPHPFile("$fileDir/{$finfo->getFileName()}.area.php", $areas);
        $this->area = [];
    }

    private function generateMacro(array $i, \C\Macro $macro)
    {
        $macroTokens = $i['function'];
        $macroTokens .= ';';
        $macroFun = \C\Reader::fromStream(\Help\IO::stringToStream($macroTokens))->next()->getElements();
        $macroElements = $macro->getElements();
        $macroFun['identifier']['name'] = $macroElements['name'];

        foreach ($macroElements['args'] as $k => $name)
            $macroFun['parameters'][$k]['identifier']['name'] = $name;

        $code = $macroElements['tokens'] . ';';

        if (false === \array_search('void', $macroFun['items']))
            $code = "return $code";

        $ret = $this->prototypeToString($macroFun);
        $ret .= "\n{ $code }";
        return "\n$ret";
    }

    private function generatePrototype(array $i, \C\Declaration $decl)
    {
        return $this->generatePrototype_($i, $decl) . ';';
    }

    private function generatePrototype_(array $i, \C\Declaration $decl)
    {
        $decl = $decl->getElements();
        $generateType = $i['function'] ?? $i['prototype'];

        if (\is_string($generateType)) {
            $pos = $decl['identifier']['pos'];
            $decl['items'][$pos] = $generateType;
        }
        return $this->prototypeToString($decl);
    }

    private function generateFunction(array $i, \C\Declaration $decl)
    {
        return "\n" . $this->generatePrototype_($i, $decl) . ($decl->getElements()['cstatement'] ?? '');
    }

    private function getSourceType(\C\ReaderElement $element): SourceType
    {
        if ($element instanceof \C\Macro)
            return SourceType::Macro;
        if ($element->getGroup() === \C\DeclarationGroup::definition)
            return SourceType::Function;

        return SourceType::Prototype;
    }

    private function getGenerateStrategy(SourceType $sourceType)
    {
        return match ($sourceType) {
            SourceType::Prototype => $this->generatePrototype(...),
            sourceType::Function => $this->generateFunction(...),
            sourceType::Macro => $this->generateMacro(...)
        };
    }

    private function flushStorage(): void
    {
        $finfo = $this->oneFileData->fileInfo;
        $groupByIds = [];
        $infosToSave = [];
        $targetInfos = [];

        // Group by target
        foreach ($this->storage as $storageItem) {
            [
                $instructionArray,
                $sourceElement
            ] = $storageItem;

            $targets = $this->getTargets((array) $instructionArray['target']);
            $tkey = $this->getTargetKey($targets);
            $sourceType = $this->getSourceType($sourceElement);
            $tags = \array_merge((array) $sourceType->value, (array) ($instructionArray['tag'] ?? null));
            \sort($tags);

            $gid = $ids[$tkey] ??= $this->groupId ++;

            foreach ($targets as $t)
                $targetInfos[$t][$gid] = null;

            $groupByIds[$tkey][] = $storageItem + [
                2 => $tags,
                $sourceType
            ];
        }
        $targetInfos = \array_map(\array_keys(...), $targetInfos);

        foreach ($groupByIds as $targets => $storageItems) {

            foreach ($storageItems as [
                $instructionArray,
                $sourceElement,
                $tags,
                $sourceType
            ]) {
                $infosToSave[$ids[$targets]][] = [
                    'tags' => $tags,
                    'text' => $this->getGenerateStrategy($sourceType)($instructionArray, $sourceElement)
                ];
            }
        }
        $fileDir = "{$finfo->getPathInfo()}/";

        if (! is_dir($fileDir))
            mkdir($fileDir, 0777, true);

        \Help\IO::printPHPFile("$fileDir/{$finfo->getFileName()}.php", $infosToSave);
        \Help\IO::printPHPFile("$fileDir/{$finfo->getFileName()}.target.php", $targetInfos);
        $this->storage = [];
    }

    // ========================================================================
    private function macroIsPCP(\C\Macro $macro): bool
    {
        return \in_array($macro->getFirstArgument(), $this->config['cpp.name']);
    }

    private function skipGenerated($stream): int
    {
        $pos = \ftell($stream);
        $reader = \C\Reader::fromStream($stream, false);
        $macro = $reader->nextMacro();

        if (null === $macro || ! $this->macroIsPCP($macro) || $macro->getCommand() !== 'begin')
            goto noBegin;

        while (true) {
            $macro = $reader->nextMacro();

            if (null === $macro || ($this->macroIsPCP($macro) && $macro->getCommand() === 'end'))
                break;
        }
        return \ftell($stream) - $pos;
        noBegin:
        $reader->close();
        return 0;
    }

    private static function includeSource(string $file): array
    {
        $ret = include "$file.php";

        foreach ($ret as $k => &$sub)
            foreach ($sub as $kk => &$item)
                $item['tags'] = \array_flip([
                    ...$item['tags'],
                    'remaining'
                ]);

        return $ret;
    }

    private function generate(): void
    {
        $filesDir = \getcwd();
        $this->goWorkingDir(self::wd);

        $cppNames = (array) $this->config['cpp.name'];
        $cppName = \Help\Arrays::first($cppNames);
        $sourceCache = [];

        $dirIterator = new \RecursiveDirectoryIterator('.', \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME);
        $dirIterator = new \RecursiveIteratorIterator($dirIterator);
        $dirIterator = new \RegexIterator($dirIterator, "/^.+\.target\.php$/");

        foreach ($dirIterator as $baseTargetFile) {
            $fileSource = \substr((string) $baseTargetFile, 0, - \strlen('.target.php'));
            $targets = include $baseTargetFile;

            $sourceInfos = $sourceCache[$fileSource] ??= self::includeSource($fileSource);

            foreach ($targets as $targetFile => $targetGIDs) {
                $writer = \File\Insertion::fromFilePath("$filesDir/$targetFile", 'tmp');
                $targetAreas = @include "$targetFile.area.php";

                if (false === $targetAreas)
                    continue;

                foreach ($targetAreas as $area) {
                    $pos = $area['pos'];
                    $writer->seek($pos);
                    $areaTags = \array_flip((array) $area['tags']);

                    if (empty($areaTags))
                        $cond = fn () => true;
                    else
                        $cond = function ($tags) use ($areaTags) {
                            $a = \array_intersect_key($areaTags, $tags);
                            return \count($a) === \count($areaTags);
                        };

                    // Test if the generation is already present
                    $rstream = $writer->getReadStream();
                    $skipped = $this->skipGenerated($rstream);

                    if ($skipped > 0) {
                        $writer->seekAdd($skipped);
                        $write = function () {};
                    } else
                        $write = function ($text) use ($writer) {
                            $writer->write($text);
                        };

                    $write("#pragma $cppName begin\n");

                    foreach ($targetGIDs as $gid) {

                        foreach ($sourceInfos[$gid] as &$sourceInfo) {

                            if (! $cond($sourceInfo['tags']))
                                continue;

                            unset($sourceInfo['tags']['remaining']);
                            $write($sourceInfo['text']);
                            $write("\n");
                        }
                    }
                    $write("#pragma $cppName end\n");
                }
                $writer->close();
            }
        }
        $this->outWorkingDir();
    }
}