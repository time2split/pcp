<?php

namespace Time2Split\PCP\Help;

use Time2Split\Help\Classes\NotInstanciable;

final class HelpFilePath
{
    use NotInstanciable;

    public static function canonical(string $path, string $delimiter = DIRECTORY_SEPARATOR): string
    {
        if (false === \strstr($path, '.'))
            return $path;

        $delimiterLen = \strlen($delimiter);
        $hasRoot = $delimiter === \substr($path, 0, $delimiterLen);

        if ($hasRoot)
            $path = \substr($path, $delimiterLen);

        $parts = \explode($delimiter, $path);
        $path = [];

        foreach ($parts as $p) {
            if ($p === '.') continue;
            if ($p === '..') {
                $c = \count($path);

                if (0 === $c)
                    $path[] = $p;
                else {
                    $previous = $path[$c - 1];

                    if ($previous === "")
                        $path[] = $p;
                    else
                        \array_pop($path);
                }
            } else
                $path[] = $p;
        }
        return ($hasRoot ? $delimiter : '') . \implode($delimiter, $path);
    }
}
