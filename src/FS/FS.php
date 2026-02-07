<?php

declare(strict_types=1);

namespace Time2Split\PCP\FS;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Container\Path;
use Time2Split\Help\Container\PathEdge;
use Time2Split\Help\Container\PathEdges;
use Time2Split\Help\Container\Paths;
use Time2Split\Help\Optional;
use Time2Split\Help\TriState;
use Time2Split\PCP\FS\Impl\FSPathImpl;

class FS
{
    use NotInstanciable;

    public static function explode(string $path, string $delimiter = DIRECTORY_SEPARATOR): array
    {
        return \explode($delimiter, $path);
    }

    public static function implode(Path $path, string $delimiter = DIRECTORY_SEPARATOR): string
    {
        $labels = [];

        foreach ($path as $edge) {
            $labels[] = $edge->getLabel();
        }

        if ($path->rooted() === TriState::Yes)
            \array_unshift($labels, '');
        if ($path->leafed() === TriState::No)
            \array_push($labels, '');

        return \implode($delimiter, $labels);
    }

    public static function current(): PathEdge
    {
        static $mem = PathEdges::current(Optional::of('.'));
        return $mem;
    }

    public static function previous(): PathEdge
    {
        static $mem = PathEdges::previous(Optional::of('..'));
        return $mem;
    }

    private static function pathOfStrings(string ...$parts): Path
    {
        $first = \array_key_first($parts);

        if ($parts[$first] === '') {
            $parts[$first] = TriState::Yes;
        }
        $last = \array_key_last($parts);

        if ($parts[$last] === '') {
            $parts[$last] = TriState::No;
        }
        foreach ($parts as &$p) {

            if ($p === '.')
                $p = self::current();
            elseif ($p === '..')
                $p = self::previous();
        }
        return Paths::of($parts);
    }

    public static function pathOf(
        FSPath|iterable $path,
        ?TriState $forceRoot = null,
        ?TriState $forceLeaf = null
    ): FSPath {

        if (!($path instanceof Path))
            $path = self::pathOfStrings(...\iterator_to_array($path));

        return new FSPathImpl(
            $forceRoot ?? $path->rooted(),
            $forceLeaf ?? $path->leafed(),
            $path
        );
    }
}
