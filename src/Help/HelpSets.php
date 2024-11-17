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

    public static function includedIn(Set $a, Set $b): bool
    {
        if (\count($a) > \count($b))
            return false;

        $aa = \iterator_to_array($a);
        $bb =  \iterator_to_array($b);

        return  \count(\array_intersect($bb, $aa)) == \count($aa);
    }
}
