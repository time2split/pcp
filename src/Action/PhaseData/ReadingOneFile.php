<?php

namespace Time2Split\PCP\Action\PhaseData;

use Time2Split\PCP\FS\FSFile;

final class ReadingOneFile extends AbstractReadingFile
{
    public readonly FSFile $fsFile;

    private function __construct(FSFile $fsFile)
    {
        $this->fsFile = $fsFile;
    }

    public final static function fromPath(FSFile $fsFile): static
    {
        return new static($fsFile);
    }
}
