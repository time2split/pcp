<?php

namespace Time2Split\PCP\Action\PhaseData;

use Time2Split\PCP\FS\FSDir;

final class ReadingDirectory extends AbstractReadingFile
{
    public readonly FSDir $fsDir;

    private function __construct(FSDir $fsDir)
    {
        $this->fsDir = $fsDir;
    }

    public final static function fromPath(FSDir $fsDir): static
    {
        return new static($fsDir);
    }
}
