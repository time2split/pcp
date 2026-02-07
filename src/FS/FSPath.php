<?php

declare(strict_types=1);

namespace Time2Split\PCP\FS;

use Time2Split\Help\Container\Path;

interface FSPath extends Path
{
    #[\Override]
    function __toString(): string;
}
