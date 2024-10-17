<?php

namespace Time2Split\PCP;

require_once __DIR__ . '/vendor/autoload.php';

$actions = [
    'process',
    'clean'
];

\array_shift($argv);

while ($action = \array_shift($argv)) {

    if (!\in_array($action, $actions))
        throw new \Exception("Unknown action '$action'");

    (new PCP())->process($action, App::configuration());
}
