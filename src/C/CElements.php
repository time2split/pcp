<?php

declare(strict_types=1);

namespace Time2Split\PCP\C;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Set;
use Time2Split\Help\Sets;
use Time2Split\PCP\C\Element\CElementType;

final class CElements
{
    use NotInstanciable;

    public static function tagsOf(CElement $element): Set
    {
        $tags = [];

        // Add the subject types as tags
        foreach ($element->getElementType() as $t)
            $tags[] = 'from.' . \strtolower($t->name);

        \sort($tags);
        $ret = Sets::arrayKeys();
        $ret->setMore(...$tags);
        return $ret;
    }

    private static CElement $null;

    public static function null()
    {
        return self::$null ??= new class() implements CElement
        {
            public function getElementType(): Set
            {
                return CElementType::of();
            }
        };
    }
}
