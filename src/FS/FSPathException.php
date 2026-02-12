<?php

declare(strict_types=1);

namespace Time2Split\PCP\FS;

use Time2Split\PCP\FS\FSPath;

class FSPathException extends \LogicException
{
    public function __construct(
        string $message = "",
        int $code = 0,
        \Throwable|null $previous = null,
        ?FSPath $fsPath = null,
    ) {
        if (isset($fsPath)) {
            $head = "($fsPath)";
            $message = "$head $message";
        }
        return parent::__construct($message, $code, $previous);
    }
}
