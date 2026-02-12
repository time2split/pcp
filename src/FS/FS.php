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
use Time2Split\PCP\FS\Impl\FSDirImpl;
use Time2Split\PCP\FS\Impl\FSFileImpl;
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
        FSPath|string|iterable $path,
        ?TriState $forceRoot = null,
        ?TriState $forceLeaf = null
    ): FSPath {

        if (\is_string($path))
            $path = self::explode($path);

        if (!($path instanceof Path))
            $path = self::pathOfStrings(...\iterator_to_array($path));

        $leafed = $forceLeaf ?? $path->leafed();
        $fsPathClass = match ($leafed) {
            TriState::Yes => FSFileImpl::class,
            TriState::No => FSDirImpl::class,
            TriState::Maybe => FSPathImpl::class,
        };
        return new $fsPathClass(
            $forceRoot ?? $path->rooted(),
            $leafed,
            $path
        );
    }

    public static function dirOf(
        FSPath|string|iterable $path,
        ?TriState $forceRoot = null,
    ): FSDir {
        return self::pathOf($path, $forceRoot, TriState::No);
    }

    public static function fileOf(
        FSPath|string|iterable $path,
        ?TriState $forceRoot = null,
    ): FSFile {
        return self::pathOf($path, $forceRoot, TriState::Yes);
    }

    /**
     * Gets a path from one parent and its subpath.
     * 
     * The resulting path is composed from the two input paths: `$root/$fs`.
     * The function ensures that the second path does not get above the first one.
     *  
     * @phpstan-return Optional<FSPath>
     * 
     * @param FSPath $root The parent path.
     * @param FSPath $fs The sub-path.
     * 
     * @return Optional (of FSPath)
     */
    public static function subPathOf(FSDir $root, FSPath $fsPath): Optional
    {
        $base = $root->canonical();
        $rel = self::relativePath($fsPath);

        if ($rel->isEmpty())
            return Optional::empty();

        $ret = $base->append($rel->get());
        return Optional::of($ret);
    }

    public static function relativePath(FSPath $fs): Optional
    {
        if ($fs->rooted() === TriState::Yes)
            return Optional::empty();

        $sub = $fs->canonical();

        if (!$sub->isCanonical())
            return Optional::empty();

        // if ($sub->count() === 0)
        //     return Optional::of(FS::dirOf('.'));

        return Optional::of($sub);
    }

    public static function rootedOrSubPath(FSDir $relativeRoot, FSPath $fs): Optional
    {
        if ($fs->rooted() === TriState::Yes)
            return Optional::of($fs);

        return self::subPathOf($relativeRoot, $fs);
    }
}
