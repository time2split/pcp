<?php

declare(strict_types=1);

namespace Time2Split\PCP\Help;

use Time2Split\Help\Classes\NotInstanciable;

final class HelpIterables
{
    use NotInstanciable;

    public static function appends(iterable ...$iterables)
    {
        foreach ($iterables as $it) {
            foreach ($it as $k => $v)
                yield $k => $v;
        }
    }
}
