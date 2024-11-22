<?php

use Time2Split\PCP\App;
use Time2Split\PCP\PCP;

require_once __DIR__ . '/vendor/autoload.php';

\array_shift($argv);

while ($action = \array_shift($argv)) {
    (new PCP(App::emptyConfiguration()->merge(
        ['pcp.action' => $action]
    )))->process();
}
