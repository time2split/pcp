<?php

declare(strict_types=1);

namespace Time2Split\PCP\Expression\Node;

use Time2Split\Config\Configuration;

interface Node
{

    public function get(Configuration $subject): mixed;
}
