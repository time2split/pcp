<?php

namespace Time2Split\PCP\Help;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Set;

final class HelpSets
{
    use NotInstanciable;

    public static function equals(Set $a, Set $b): bool
    {
        return \count($a) === \count($b) &&
            \iterator_to_array($a) == \iterator_to_array($b);
    }
}
