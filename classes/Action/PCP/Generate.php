<?php
namespace Action\PCP;

class Generate extends \Action\BaseAction
{

    private const wd = 'generate';

    private const dirArea = 'area';

    private const fileTargets = 'targets.php';

    private const DefaultConfig = [
        'target' => '.',
        'prefix' => '',
        'always.prototype' => false,
        'always.function' => false
    ];

    private \Data\TreeConfig $storage;

    private \Data\TreeConfig $myConf;

    private array $targetInfos = [];

    private int $groupId = 0;

    private ?\C\Macro $nextInstruction;

    public function __construct(\Data\TreeConfig $config)
    {
        parent::__construct($config);
        $this->storage = \Data\TreeConfig::empty();
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
                    $this->storage["macro&"][] = [
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
                    if (! \is_dir($d = self::wd . '/' . self::dirArea))
                        mkdir($d);

                    $this->outWorkingDir();
                } elseif (\Action\PhaseState::Stop == $phase->state) {
                    $this->flushWD();
                    $this->generate();
                }
                break;

            case \Action\PhaseName::ReadingOneFile:

                if (\Action\PhaseState::Start == $phase->state) {
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

        $args = $next->getArguments();

        if (isset($args['function']))
            $args['storage.group'] = 'function';
        else
            $args['storage.group'] = 'prototype';

        return $next->setArguments($args);
    }

    private function doInstruction(\C\Macro $inst): void
    {
        $args = $inst->getArguments();

        if (isset($args['area'])) {
            $this->storage["area&"][] = [
                'sourceGroups' => $args['area'],
                'pos' => $inst->getFileCursors()[1]->getPos(),
                'date' => $this->config['dateTime']
            ];
        } else {

            if (isset($args['function']) || isset($args['prototype'])) {

                if (isset($this->nextInstruction))
                    throw new \Exception("Cannot execute the instruction '$inst' because another one is already waiting '$this->nextInstruction'");

                $this->nextInstruction = $inst;
            } else
                $this->myConf->arrayMerge($args);
        }
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
                $t = (string) $this->config['fileInfo'];
            elseif (false === \strpos('/', $t) || \str_starts_with('./', $t))
                $t = "{$this->config['fileInfo']->getPathInfo()}/$t";

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
        $group = $iargs['storage.group'];
        $this->storage["$group&"][] = [
            $iargs + $this->storeSelectConf(),
            $decl
        ];
    }

    private function flushWD(): void
    {
        $this->goWorkingDir(self::wd);
        $this->flushTargetInfos();
        $this->outWorkingDir();
    }

    private function flushFileInfos(): void
    {
        $this->goWorkingDir(self::wd);
        $this->flushStorageElements();
        $this->storage->clearLevel();
        $this->outWorkingDir();
    }

    private function flushArea(): void
    {
        $areas = \array_filter((array) $this->storage['area']);

        if (empty($areas))
            return;

        $finfo = $this->config['fileInfo'];
        $fileDir = self::dirArea . "/{$finfo->getPathInfo()}/";

        if (! is_dir($fileDir))
            mkdir($fileDir, 0777, true);

        \Help\IO::printPHPFile("$fileDir/{$finfo->getFileName()}", $areas);
    }

    private function flushTargetInfos(): void
    {
        \Help\IO::printPHPFile(self::fileTargets, $this->targetInfos);
    }

    private function flushStorageElements(): void
    {
        foreach (self::storageElements as $k)
            $this->flushStorage($k);

        $this->flushTargetInfos();
        $this->flushArea();
    }

    private function generateMacro(array $i, \C\Macro $macro)
    {
        $macroTokens = $i['function'];
        $macroTokens .= ';';
        $macroFun = \C\Reader::fromStream(\Help\IO::stringToStream($macroTokens))->next();
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

    private function flushStorage($storageKey): void
    {
        $funSave = match ($storageKey) {
            'prototype' => $this->generatePrototype(...),
            'function' => $this->generateFunction(...),
            'macro' => $this->generateMacro(...)
        };
        $finfo = $this->config['fileInfo'];
        $ids = [];
        $groupByIds = [];
        $s = (array) $this->storage[$storageKey];

        // Group by target
        foreach ($s as $p) {
            list ($instruction, $declSource) = $p;
            $targets = $this->getTargets((array) $instruction['target']);
            $tkey = $this->getTargetKey($targets);

            if (! isset($ids[$tkey])) {
                $gid = $ids[$tkey] ??= $this->groupId ++;

                foreach ($targets as $t)
                    $this->targetInfos[$t][$storageKey]["$finfo"][] = $gid;
            }
            $groupByIds[$tkey][] = $p;
        }
        $infosToSave = [];

        foreach ($groupByIds as $k => $v) {
            foreach ($v as $infos) {
                list ($instruction, $declSource) = $infos;
                $infosToSave[$ids[$k]][] = $funSave($instruction, $declSource);
            }
        }
        $fileDir = "$storageKey/{$finfo->getPathInfo()}/";

        if (! is_dir($fileDir))
            mkdir($fileDir, 0777, true);

        \Help\IO::printPHPFile("$fileDir/{$finfo->getFileName()}", $infosToSave);
    }

    // ========================================================================
    private function getAreaSourceGroups(array $area): array
    {
        $sourceGroups = $area['sourceGroups'];

        if (true === $sourceGroups)
            $sourceGroups = [
                'prototype',
                'function',
                'macro'
            ];
        return $sourceGroups;
    }

    private function generate(): void
    {
        $filesDir = \getcwd();
        $this->goWorkingDir(self::wd);

        $cppNames = (array) $this->config['cpp.name'];
        $cppName = \Help\Arrays::first($cppNames);
        $targets = include self::fileTargets;

        foreach ($targets as $fileTarget => $sourcesGroups) {
            $writer = \File\Insertion::fromFilePath("$filesDir/$fileTarget", 'tmp');
            $targetAreas = @include self::dirArea . "/$fileTarget";

            if (! isset($targetAreas))
                continue;
            if (($c = \count($targetAreas)) > 1)
                throw new \Exception("Cannot handle more than 1 generate area for now; $fileTarget has $c");

            foreach ($targetAreas as $area) {
                $genGroups = $this->getAreaSourceGroups($area);
                $pos = $area['pos'];
                $writer->seek($pos);

                // Test if the generation is already present
                $rstream = $writer->getReadStream();
                \fseek($rstream, $pos, SEEK_SET);
                $cpp = \C\Reader::getNextCpp($rstream);

                if (! empty($cpp)) {
                    $cpp = \C\Macro::fromReaderElements($cpp, $cppNames);

                    if ($cpp->getCommand() === 'begin') {
                        $writer->close();
                        continue;
                    }
                }
                $writer->write("#pragma $cppName begin\n");

                foreach ($sourcesGroups as $sourceGroup => $sourcesFiles) {

                    foreach ($sourcesFiles as $sourceFile => $genIds) {
                        $sourceData = include "$sourceGroup/$sourceFile";

                        foreach ($genIds as $genId) {

                            $gen = implode("\n", $sourceData[$genId]);
                            $writer->write($gen);
                            $writer->write("\n");
                        }
                    }
                }
                $writer->write("#pragma $cppName end\n");
            }
            $writer->close();
        }
        $this->outWorkingDir();
    }
}