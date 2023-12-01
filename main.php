<?php
require_once __DIR__ . '/classes/autoload.php';

$CONFIG = [
    'cpp.wd' => getcwd() . '/cpp.wd',
    'pragmas_fileConfig' => __DIR__ . '/config.php',
    'debug' => false,
    // 'cleaned' => null,
    'cpp.name' => [
        'zrlib',
        'pcp'
    ],
    'paths' => [
        'src'
    ]
];

$actions = [
    'process',
    'clean'
];
$action = $argv[1] ?? 'process';

if (! \in_array($action, $actions))
    throw new \Exception("Unknown action '$action'");

$CONFIG['action'] = $action;
$theConfig = \Data\TreeConfigHierarchy::create();
$theConfig->mergeArrayRecursive($CONFIG);

(new \Process\DoIt())->process($theConfig);
