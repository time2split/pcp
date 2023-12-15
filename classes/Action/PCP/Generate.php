<?php
namespace Action\PCP;

use C\Element\Macro;
use C\Element\Declaration;

class Generate extends \Action\BaseAction
{

    private const wd = 'generate';

    private const DefaultConfig = [
        'generate' => [
            'always.prototype' => false,
            'always.function' => false,

            'tags' => null,
            'targets' => '.',
            'targets.prototype' => '${targets}',

            'name.base' => null,
            'name.prefix' => null,
            'name.suffix' => null,
            'name.format' => '${name.prefix}${name.base}${name.suffix}'
        ]
    ];

    private Generate\Instruction\Factory $ifactory;

    private Generate\Instruction\Storage $istorage;

    private array $area;

    private ?Macro $nextInstruction;

    private \Action\PhaseData\ReadingOneFile $oneFileData;

    public function __construct(\Data\IConfig $config)
    {
        parent::__construct($config);
        $this->config = $this->config->child();
        $this->ifactory = new Generate\Instruction\Factory();
        $this->istorage = new Generate\Instruction\Storage($config);
        $this->area = [];
    }

    public function onMessage(\Action\IActionMessage $msg): void
    {
        if ($msg instanceof Macro) {

            if ($msg->getDirective() === 'pragma' && $msg->getCommand() === 'generate')
                $this->doInstruction($msg);
            elseif ($msg->getDirective() === 'define') {
                throw new \Exception('Not implemented: ' . __file__);
            }
        } elseif ($msg instanceof Declaration) {

            switch ($msg->getType()) {

                case \C\DeclarationType::tfunction:
                    $instruction = $this->nextInstruction($msg);

                    if (! isset($instruction))
                        break;

                    if ( //
                    $msg->getGroup() === \C\DeclarationGroup::definition || //
                    $msg->getGroup() === \C\DeclarationGroup::declaration && $msg->getType() === \C\DeclarationType::tfunction) {
                        $first = $instruction->getArguments();
                        $secnd = $this->config->subConfig('generate');

                        $i = \Data\TreeConfig::emptyOf($this->config);
                        $i->merge($first);
                        $i->merge($secnd);
                        $i = $this->decorateConfig($i);

                        $this->istorage->add($this->ifactory->create($msg, $i));
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
                    $this->config->clear();
                    $this->config->merge(self::DefaultConfig);
                } elseif (\Action\PhaseState::Stop == $phase->state) {
                    $this->flushFileInfos();
                }
                break;
        }
    }

    // ========================================================================
    private function nextMacroInstruction(Macro $macro): ?Macro
    {
        if (isset($this->nextInstruction)) {
            $next = $this->nextInstruction;
            $this->nextInstruction = null;
            return $next;
        }
        return null;
    }

    private function nextInstruction(Declaration $decl): ?Macro
    {
        $next = null;

        if (! isset($this->nextInstruction)) {

            // Function definition
            if ($decl->getType() === \C\DeclarationType::tfunction && $decl->getGroup() === \C\DeclarationGroup::definition) {

                if (($p = isset($this->myConfig['always.prototype'])) && isset($this->myConfig['always.function']));
                elseif ($p)
                    $next = Macro::fromText('generate prototype');
                else
                    $next = Macro::fromText('generate function');
            }
        } else {
            $next = $this->nextInstruction;
            $this->nextInstruction = null;
        }
        return $next;
    }

    private function doInstruction(Macro $inst): void
    {
        $args = $inst->getArguments();

        if (isset($args['area'])) {
            $this->area[] = [
                'tags' => $args['tags'] ?? null,
                'pos' => $inst->getFileCursors()[1]->getPos(),
                'date' => $this->config['dateTime']
            ];
        } elseif (isset($args['function']) || isset($args['prototype'])) {

            if (isset($this->nextInstruction))
                throw new \Exception("Cannot execute the instruction '$inst' because another one is already waiting '$this->nextInstruction'");

            $this->nextInstruction = $inst;
        } else {
            $args = \Help\Arrays::map_key(fn ($k) => "generate.$k", $args);
            $this->config->merge($args);
        }
    }

    // ========================================================================
    private function flushStorage(): void
    {
        $this->istorage->flushOnFile($this->oneFileData);
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

    private function generateName(string $baseName): string
    {
        $conf = $this->config;
        $conf['generate.name.base'] = $baseName;
        return $conf['generate.name.format'];
    }

    private function generateMacro(array $i, Macro $macro)
    {
        $macroTokens = $i['function'];
        $macroTokens .= ';';

        $macroFun = \C\Reader::fromStream(\Help\IO::stringToStream($macroTokens))->next();
        $macroElements = $macro->getElements();
        $macroFun['identifier']['name'] = $this->generateName($macroElements['name']);

        $macroFunParameters = $macroFun->getParameters();

        foreach ($macroElements['args'] as $k => $name)
            $macroFunParameters[$k]['identifier']['name'] = $name;

        $code = $macroElements['tokens'] . ';';

        if (false === \array_search('void', $macroFun['items']))
            $code = "return $code";

        $ret = $this->prototypeToString($macroFun);
        $ret .= "\n{ $code }";
        return "\n$ret";
    }

    private function generateFunction(array $i, Declaration $decl): string
    {
        return "\n" . $this->generatePrototype_($i, $decl) . ($decl->getElements()['cstatement'] ?? '');
    }

    private function getGenerateStrategy(SourceType $sourceType): callable
    {
        return match ($sourceType) {
            SourceType::Prototype => $this->generatePrototype(...),
            sourceType::Function => $this->generateFunction(...),
            sourceType::Macro => $this->generateMacro(...)
        };
    }

    // ========================================================================
    private function macroIsPCP(Macro $macro): bool
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