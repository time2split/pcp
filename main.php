<?php
namespace Time2Split\PCP;

use Time2Split\Config\Interpolators;
use Time2Split\Config\TreeConfigBuilder;
use Time2Split\PCP\C\PCP;
require_once __DIR__ . '/vendor/autoload.php';

$CONFIG = [
    'cpp.wd' => getcwd() . '/cpp.wd',
    'debug' => false,
    'cpp.name' => [
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

$theConfig = TreeConfigBuilder::builder()->setDelimiter('.')
    ->setContent($CONFIG)
    ->setInterpolator(Interpolators::recursive())
    ->build();

(new PCP())->process($theConfig);
