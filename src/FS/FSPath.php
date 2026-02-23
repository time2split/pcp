<?php

declare(strict_types=1);

namespace Time2Split\PCP\FS;

use Time2Split\Help\Container\Path;
use Time2Split\Help\Optional;
use Time2Split\Help\TriState;

interface FSPath extends Path
{
    #[\Override]
    function __toString(): string;

    function setRooted(TriState $rooted): FSPath;

    function setLeafed(TriState $leafed): FSPath;

    function info(): \SplFileInfo;

    function append(FSPath|string|iterable $path, ?TriState $forceLeafed = null): FSPath;

    function appendDir(FSPath|string|iterable $path): FSPath;

    function appendFile(FSPath|string|iterable $path): FSPath;

    function hasPrefix(FSPath $fsPrefix): bool;

    function removePrefix(FSPath $fsPrefix): Optional;

    function inFS(bool $checkLeafType = true): bool;

    function setLast(FSPath|string|\Closure $setLast);
}
