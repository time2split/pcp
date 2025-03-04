<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Set;
use Time2Split\Help\Sets;

enum CElementType: int
{

    case CPP = 512;

    case Function = 256;

    case Variable = 128;

    case Declaration = 1;

    case Definition = 2;

    public static function ofCPP(): Set
    {
        return self::of(self::CPP);
    }

    public static function ofCPPDefine(): Set
    {
        return self::of(self::CPP, self::Definition);
    }

    public static function ofCPPDefineFunction(): Set
    {
        return self::of(self::CPP, self::Definition, self::Function);
    }

    public static function ofVariableDeclaration(): Set
    {
        return self::of(self::Variable, self::Declaration);
    }

    public static function ofFunctionDeclaration(): Set
    {
        return self::of(self::Function, self::Declaration);
    }

    public static function ofFunctionDefinition(): Set
    {
        return self::of(self::Function, self::Definition);
    }

    // ========================================================================

    private const AllowedTypes = [
        [],
        [self::CPP],
        [self::CPP, self::Definition],
        [self::CPP, self::Definition, self::Function],
        [self::Variable, self::Declaration],
        [self::Function, self::Declaration],
        [self::Function, self::Definition],
    ];

    private static function getCacheIndex(self ...$types): int
    {
        $i = 0;

        foreach ($types as $t)
            $i |= $t->value;

        return $i;
    }

    private static function createSet(self ...$types): Set
    {
        return (Sets::ofBackedEnum(self::class))
            ->setMore(...$types);
    }

    // ========================================================================

    /**
     * @throws \InvalidArgumentException
     * @return Set<CElementType> An unmodifiable set of types.
     * The obtained set is comparable to previous returned sets with ===.
     */
    public static function of(self ...$types): Set
    {
        if (!\in_array($types, self::AllowedTypes, true)) {
            $types = \implode(',', \array_map(fn($t) => $t->name, $types));
            throw new \InvalidArgumentException(__METHOD__ . "Unknown combinainon of " . self::class . "($types)");
        }
        static $cache = [];
        $pos = self::getCacheIndex(...$types);

        if (isset($cache[$pos]))
            return $cache[$pos];

        return $cache[$pos] = Sets::unmodifiable(self::createSet(...$types));
    }

    public static function stringOf(Set $type): string
    {
        return \implode(',', self::namesOf($type));
    }

    public static function namesOf(Set $type): array
    {
        $ret = [];

        foreach ($type as $t)
            $ret[] = $t->name;

        return $ret;
    }
}
