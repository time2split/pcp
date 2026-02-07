<?php

namespace Time2Split\PCP\FS\Impl;

use Time2Split\Help\Container\Impl\PathImpl;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSPath;

class FSPathImpl
extends PathImpl
implements FSPath
{
    #[\Override]
    public function __toString(): string
    {
        return FS::implode($this);
    }
}
