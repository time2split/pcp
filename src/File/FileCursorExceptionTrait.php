<?php

namespace Time2Split\PCP\File;

use Time2Split\PCP\File\FileCursor;

trait FileCursorExceptionTrait
{
    private function makeMessage(string $message, ?FileCursor $fileCursor = null): string
    {
        $fileCursor ??= $this->fileCursor;

        if (null === $fileCursor)
            return '';

        $cursorPosition = $fileCursor->cursorPosition;
        $line = $cursorPosition->line + 1;
        return "$message on file $fileCursor->file at line $line ($fileCursor->cursorPosition)";
    }
}
