<?php

use Time2Split\PCP\App\Bootstrap;

require_once __DIR__ . '/vendor/autoload.php';

\array_shift($argv);
Bootstrap::bootstrap($argv);
