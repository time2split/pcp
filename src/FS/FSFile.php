<?php

declare(strict_types=1);

namespace Time2Split\PCP\FS;

interface FSFile extends FSPath
{
    function setFile(string $fileName): FSFile;

    function getPath(): FSDir;
}
