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
        'always.prototype' => false
    ];

    private \Data\TreeConfig $storage;

    private \Data\TreeConfig $myConf;

    private array $targetInfos = [];

    private int $groupId = 0;

    public function __construct(\Data\TreeConfig $config)
    {
        parent::__construct($config);
        $this->storage = \Data\TreeConfig::empty();
    }

    public function onMessage(\Action\IActionMessage $msg): void
    {
        if ($msg instanceof \Action\Instruction && $msg->getCommand() === 'generate')
            $this->doInstruction($msg);
        elseif ($msg instanceof \C\Declaration) {

            switch ($msg->getType()) {

                case \C\DeclarationType::tfunction:

                    if ($this->myConf['always.prototype'] || isset($this->myConf['prototype'])) {
                        $this->storeGroup('prototype', $msg);
                        unset($this->myConf['prototype']);
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
    private function doInstruction(\Action\Instruction $inst): void
    {
        $args = \Help\Arrays::listValueAsKey($inst->getArguments(), true);

        if (isset($args['area'])) {
            $this->storage["area&"][] = [
                'sourceGroups' => $args['area'],
                'pos' => $inst->getFileCursors()[1]->getPos(),
                'date' => $this->config['dateTime']
            ];
        } else
            $this->myConf->arrayMerge($args);
    }

    // ========================================================================
    private function functionPrototypeToString(array $declaration): string
    {
        $fp = \Help\IO::stringToStream();
        $this->printFunctionPrototype($fp, $declaration);
        \fwrite($fp, ';');
        \rewind($fp);
        return \stream_get_contents($fp);
    }

    private function printFunctionPrototype($fp, array $declaration): void
    {
        $e = $declaration;
        $lastIsAlpha = false;

        foreach ($e['items'] as $s) {
            $len = \strlen($s);

            if ($len == 0)
                continue;

            if ($lastIsAlpha && ! \ctype_punct($s)) {
                fwrite($fp, " $s");
            } else {
                $lastIsAlpha = $len > 0 ? \ctype_alpha($s[$len - 1]) : false;
                fwrite($fp, $s);
            }
        }

        if ($declaration['type'] == \C\DeclarationType::tfunction) {
            $params = $e['parameters'] ?? null;

            fwrite($fp, "(");

            if (! empty($params)) {
                $active = false;

                $this->printFunctionPrototype($fp, \array_shift($params));

                foreach ($params as $p) {
                    fwrite($fp, ', ');
                    $this->printFunctionPrototype($fp, \array_shift($params));
                }
            }
            fwrite($fp, ")");
        }
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
        'prototype'
    ];

    private function storeGroup(string $group, \C\Declaration $declaration): void
    {
        $this->storage["$group&"][] = [
            \Help\Arrays::subSelect($this->myConf->toArray(), [
                'target',
                'prefix'
            ]),
            $declaration
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

    private function flushStorage($storageKey): void
    {
        $finfo = $this->config['fileInfo'];
        $ids = [];
        $groupByIds = [];
        $s = (array) $this->storage[$storageKey];

        // Group by target
        foreach ($s as $p) {
            $targets = $this->getTargets((array) $p[0]['target']);
            $tkey = $this->getTargetKey($targets);

            $gid = $ids[$tkey] ??= $this->groupId ++;

            foreach ($targets as $t)
                $this->targetInfos[$t][$storageKey]["$finfo"][] = $gid;

            $groupByIds[$tkey][] = $p[1];
        }
        $infosToSave = [];

        foreach ($groupByIds as $k => $v)
            foreach ($v as $decl)
                $infosToSave[$ids[$k]][] = $this->functionPrototypeToString($decl->getElement());

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
                'prototype'
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

        foreach ($targets as $target => $sourcesGroup) {
            $writer = \File\Insertion::fromFilePath("$filesDir/$target", 'tmp');
            $targetAreas = @include self::dirArea . "/$target";

            if (! isset($targetAreas))
                continue;
            if (($c = \count($targetAreas)) > 1)
                throw new \Exception("Cannot handle more than 1 generate area for now; $target has $c");

            foreach ($targetAreas as $area) {
                $genGroups = $this->getAreaSourceGroups($area);
                $pos = $area['pos'];
                $writer->seek($pos);

                $rstream = $writer->getReadStream();
                \fseek($rstream, $pos, SEEK_SET);
                $cpp = \C\Reader::getNextCpp($rstream);

                if (! empty($cpp)) {
                    $cpp = \Action\Instruction::fromReaderElement($cpp, $cppNames);

                    if ($cpp->getCommand() === 'begin') {
                        $writer->close();
                        continue;
                    }
                }
                $writer->write("#pragma $cppName begin\n");

                foreach ($genGroups as $genGroup) {
                    $sources = $sourcesGroup[$genGroup];

                    foreach ($sources as $sourceFile => $sourceGroups) {
                        $sourceData = include "$genGroup/$sourceFile";

                        foreach ($sourceGroups as $sourceGroup)
                            foreach ($sourceData[$sourceGroup] as $prototype) {
                                $writer->write($prototype);
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