<?php

declare(strict_types=1);

namespace Time2Split\PCP\C;

use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\File\CursorPosition;

interface PCPReader
{
    public function next(): CElement|ActionCommand|null;

    public function getCursorPosition(): CursorPosition;

    public function close(): void;
}
