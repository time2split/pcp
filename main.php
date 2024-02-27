<?php
namespace Time2Split\PCP;

use Time2Split\Config\TreeConfigBuilder;
use Time2Split\PCP\Expression\Expressions;
require_once __DIR__ . '/vendor/autoload.php';

$CONFIG = [
    'pcp' => [
        'dir' => getcwd() . '/cpp.wd',
        'reading.dir.configFiles' => 'pcp.conf',
        'name' => [
            'pcp'
        ]
    ],
    'paths' => [
        'src'
    ]
];

$actions = [
    'process',
    'clean'
];

\array_shift($argv);

while ($action = \array_shift($argv)) {

    if (! \in_array($action, $actions))
        throw new \Exception("Unknown action '$action'");

    $CONFIG['action'] = $action;

    $theConfig = App::getConfigBuilder()->mergeTree($CONFIG)->build();

    (new PCP())->process($theConfig);
}