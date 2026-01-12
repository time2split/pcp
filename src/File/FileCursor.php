<?php

namespace Time2Split\PCP\File;

use Time2Split\PCP\File\CursorPosition;

final class FileCursor
{
    public function __construct(
        public readonly \SplFileInfo $file,
        public readonly CursorPosition $cursorPosition
    ) {}

    public function setCursorPosition(CursorPosition $cursorPosition): self
    {
        return new self($this->file, $cursorPosition);
    }
}
