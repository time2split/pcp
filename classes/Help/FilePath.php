<?php
namespace Help;

final class FilePath
{

    private function __construct()
    {
        throw new \Error();
    }

    public static function relativeOutOfBound(string $path): bool
    {
        $path = self::normalize($path);
        return str_starts_with($path, '..') || str_starts_with($path, '/..');
    }

    public static function normalize(string $path): string
    {
        $ret = [];
        $rooted = ($path[0] ?? '') === '/';
        $path = \trim($path, '/');

        foreach (\explode('/', $path) as $p) {
            if ($p === '.')
                continue;
            if ($p === '..' && ! empty($ret))
                \array_pop($ret);
            elseif (! str_empty($p))
                $ret[] = $p;
        }
        return ($rooted ? '/' : '') . \implode('/', $ret);
    }

    public static function parentPaths(string $path, bool $normalize = false): array
    {
        if ($normalize)
            $path = self::normalize($path);

        $ret = [];
        $buff = '';
        $d = '';

        foreach (\explode('/', $path) as $part) {
            $buff .= "$d$part";
            $ret[] = $buff;
            $d = '/';
        }
        return $ret;
    }
}