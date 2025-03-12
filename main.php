<?php
namespace Time2Split\PCP;

use Time2Split\Config\TreeConfigBuilder;
use Time2Split\PCP\Expression\Expressions;
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
    ->setInterpolator(Expressions::interpolator())
    ->build();

(new PCP())->process($theConfig);
