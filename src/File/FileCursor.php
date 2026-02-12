<?php

namespace Time2Split\PCP\File;

use Time2Split\PCP\File\CursorPosition;
use Time2Split\PCP\FS\FSFile;

final class FileCursor
{
    public function __construct(
        public readonly FSFile $fsFile,
        public readonly CursorPosition $cursorPosition
    ) {}

    public function setCursorPosition(CursorPosition $cursorPosition): self
    {
        return new self($this->fsFile, $cursorPosition);
    }
}
