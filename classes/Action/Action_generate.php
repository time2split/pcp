<?php
namespace Action;

class Action_generate implements IAction
{

    private \C\Pragma $zrpragma;

    public function __construct(\C\Pragma $zrpragma)
    {
        $this->zrpragma = $zrpragma;
    }

    public function process(array $pragma, $file)
    {
        $creader = new \C\Reader($file);

        return;
        $filePath = $this->zrpragma->getFilePath();
        $conf = &$this->zrpragma->getConfig();

        if (! isset($conf['generate.target']))
            throw new \InvalidArgumentException("Action 'generate' require config 'generate.target'");

        $query = $pragma['query'];
        $what = \array_shift($query);

        if (! \in_array($what, [
            'function'
        ]))
            throw new \BadMethodCallException("zrlib: action: 'generate' $what not defined");

        $query = implode(' ', (array) $query);
        $thing = $this->zrpragma->nextDefineOrFunction($file);
        $thingIsDefine = \array_key_exists('define', $thing);

        // Function definition
        if (0 === preg_match("/^\w+$/", $query)) {
            $tmpStream = fopen("php:memory", "w+");
            fwrite($tmpStream, $query);
            rewind($tmpStream);
            $pragmaFun = $this->zrpragma->nextCFunction($tmpStream);
            fclose($tmpStream);

            $nargsa = count($pragmaFun['arguments']);
            $nargsb = count($thing['arguments']);

            if ($nargsa !== $nargsb)
                throw new Exception("Pragma function and definition must have the same number of arguments: $nargsb, $nargsa");

            if ($thingIsDefine) {

                for ($i = 0; $i < $nargsa; $i ++) {
                    $thingArg = $thing['arguments'][$i];
                    $pragmaFun['arguments'][$i]['name'] = $thingArg;
                    $pragmaFun['arguments'][$i]['s'] = $pragmaFun['arguments'][$i]['type'] . ' ' . $thingArg;
                }
                $pragmaFun['code'] = $thing['define'];
                $data = $pragmaFun;
            } else {
                throw new Exception("A define macro was attempted");
            }
        } elseif ($thingIsDefine)
            throw new Exception("A function was attempted");
        else {
            $data = $thing;
            $data['call'] = $thing['name'];
            $data['name'] = $query;
        }
        $pragma['data'] = $data;
        $pragma['conf'] = $conf;

        return $pragma;
    }

    private function flush_generate_function(array $pragmas): string
    {
        $ret = "";

        foreach ($pragmas as $pragma) {
            $return = \in_array('void', $pragma['data']['qualifiers']) ? null : 'return ';
            $args = \implode(', ', \array_column($pragma['data']['arguments'], 'name'));
            $code = $pragma['data']['code'] ?? "{$pragma['data']['call']}($args)";
            $funcName = $pragma['data']['name'];
            $qualif = \implode(' ', $this::clean_qualifiers($pragma['data']['qualifiers']));
            $args_def = \implode(', ', \array_column($pragma['data']['arguments'], 's'));
            $ret .= <<<"END"

            $qualif {$pragma['conf']['generate.prefix']}$funcName($args_def)
            {
            	$return$code;
            }

            END;
        }
        return $ret;
    }

    private static function clean_qualifiers(array $qualifiers): array
    {
        $avoid = [
            'static',
            'inline',
            'ZRMUSTINLINE'
        ];
        return \array_filter($qualifiers, fn ($a) => ! \in_array($a, $avoid));
    }

    private function flush_generate_headers(array $pragmas): string
    {
        $ret = "";

        foreach ($pragmas as $pragma) {
            $funcName = $pragma['data']['name'];
            $qualif = \implode(' ', $this::clean_qualifiers($pragma['data']['qualifiers']));
            $args_def = \implode(', ', \array_column($pragma['data']['arguments'], 's'));
            $args = \implode(', ', \array_column($pragma['data']['arguments'], 'name'));
            $return = \in_array('void', $pragma['data']['qualifiers']) ? null : 'return ';
            $ret .= <<<"END"

            $qualif {$pragma['conf']['generate.prefix']}$funcName($args_def);
            END;
        }
        return $ret;
    }

    private function computeSkip(array $targets): array
    {
        $filePath = $this->zrpragma->getFilePath();
        $skip = [];

        foreach ($targets as $target) {
            // echo $filePath, \filemtime($filePath), ' ', $target, \filemtime($target), "\n";

            if ($target === $filePath)
                $filePathIsTarget = true;
            elseif (\in_array($target, $skip)) {} elseif (\filemtime($filePath) < \filemtime($target)) {
                $skip[] = $target;

                if (! $this->zrpragma->getConfig('debug'))
                    continue;

                echo "$filePath: generate; Skip generate.target $target\n";
            }
        }
        return $skip;
    }

    private function flush_targetted(array $targetted)
    {
        $filePath = $this->zrpragma->getFilePath();
        $mainConfig = $this->zrpragma->getConfig();
        echo "Generations for source $filePath\n";
        $updatedFiles = [];

        foreach ($targetted as $tpragmas) {
            $pragmas = $tpragmas['pragmas'];
            $targets = $tpragmas['conf']['targets'];
            // $skip = $tpragmas['conf']['skip'];
            $skip = [];
            $filePathIsTarget = $filePath === $targets[0];

            $what = [
                'functions' => []
            ];
            $types = [
                'functions',
                'headers'
            ];

            foreach ($pragmas as $pragma)
                $what[\array_shift($pragma['query'])][] = $pragma;

            $functions_s = $this->flush_generate_function($what['function']);
            $headers_s = $this->flush_generate_headers($what['function']);

            $computeFilePathTarget = false;
            $skipAll = false;

            if ($mainConfig['cleaned']) {
                $computeFilePathTarget = false;
            } elseif ($filePathIsTarget) {
                $computeFilePathTarget = true;
            } else {
                $computeFilePathTarget = false;
                $skip = $this->computeSkip($targets);
            }

            foreach ($targets as $target) {
                $target = $target;

                echo "Target $target: ";

                if ($skipAll || \in_array($target, $skip)) {
                    echo "Skipped\n";
                    continue;
                }
                $file = \fopen($target, 'r');
                $tmpFile = \tmpfile();

                $pragmas = [];
                while (false !== ($pragma = $this->zrpragma->nextPragma($file)))
                    $pragmas[] = $pragma + [
                        'pos_end' => \ftell($file)
                    ];

                $lastPos = 0;

                for ($i = 0, $c = \count($pragmas); $i < $c; $i ++) {
                    $pragma = $pragmas[$i];

                    if ($pragma['action'] !== 'write' || ($pragma['query'][0] ?? null) !== 'generate' || ! \in_array($pragma['query'][1], $types))
                        continue;

                    $pos = $pragma['pos_end'];
                    \fwrite($tmpFile, \stream_get_contents($file, $pos - $lastPos, $lastPos));
                    $lastPos = $pos;

                    switch ($pragma['query'][1]) {
                        case 'functions':
                            $functions = <<<"END"
                            #pragma zrlib begin
                            $functions_s
                            #pragma zrlib end

                            END;
                            \fwrite($tmpFile, $functions);
                            break;
                        case 'headers':
                            $functions = <<<"END"
                            #pragma zrlib begin
                            $headers_s
                            #pragma zrlib end

                            END;
                            \fwrite($tmpFile, $functions);
                            break;
                    }
                    // skip begin end
                    $nextPragma = $pragmas[$i + 1] ?? null;

                    if (null !== $nextPragma && $pragma['pos_end'] === $nextPragma['pos'] && $nextPragma['action'] === 'begin') {
                        $i += 2;
                        $lastPos = $pragmas[$i]['pos_end'];
                    }
                }
                \fwrite($tmpFile, \stream_get_contents($file, - 1, $lastPos));
                $content = \stream_get_contents($tmpFile, - 1, 0);
                $fsize = \fstat($file)['size'];
                $tmpsize = \fstat($tmpFile)['size'];
                \fclose($file);
                $updatedFiles[] = $target;

                if ($fsize !== $tmpsize || $content !== \file_get_contents($target)) {
                    \file_put_contents($target, $content);
                    echo "Update\n";
                } else {
                    if ($computeFilePathTarget)
                        $skipAll = true;

                    echo "No update\n";
                }
                \fclose($tmpFile);
            }
        }

        if ($filePathIsTarget)
            unset($updatedFiles[0]);

        /* Set all updated files to the same mtime */
        $time = \filemtime($filePath) + 1;

        foreach ($updatedFiles as $ufilePath) {
            \touch($ufilePath, $time);
        }
    }

    public function flush(array $pragmas)
    {
        /* Group by targets */
        $lastTargetConf = null;
        $confMap = [];
        $targetted = [];

        foreach ($pragmas as $pragma) {
            $currentTargetConf = $pragma['conf']['generate.target'];

            if (! \in_array($currentTargetConf, $confMap)) {
                $key = \count($confMap);
                $confMap[] = $currentTargetConf;
                $targetted[] = [
                    'conf' => [
                        'targets' => $currentTargetConf,
                        'skip' => []
                    ],
                    'pragmas' => []
                ];
            } else
                $key = \array_search($confMap, $currentTargetConf);

            unset($pragma['conf']['generate.target']);
            $targetted[$key]['pragmas'][] = $pragma;
        }
        $filePath = $this->zrpragma->getFilePath();

        /* Set self $filePath target in the first place */
        foreach ($targetted as &$tpragmas) {
            $targets = &$tpragmas['conf']['targets'];
            $targetKey = \array_search($filePath, $targets);

            if (null !== $targetKey) {
                $target = $targets[$targetKey];
                unset($targets[$targetKey]);
                \array_unshift($targets, $target);
                $targets = \array_values($targets);
            }
        }
        $this->flush_targetted($targetted);
    }
}