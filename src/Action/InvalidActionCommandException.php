<?php

namespace Time2Split\PCP\Action;

use Time2Split\Help\Functions;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\File\FileCursor;
use Time2Split\PCP\File\FileCursorExceptionTrait;
use Time2Split\PCP\PCPException;

final class InvalidActionCommandException extends PCPException
{
    use FileCursorExceptionTrait;

    public function __construct(
        protected readonly ActionCommand $command,
        protected readonly ?FileCursor $fileCursor = null,
        string $message = ''
    ) {
        $args = Functions::basicToString($command->getArguments()->toArray());
        $cmdText = "{$command->getName()} $args";
        $message = "Invalid action command:\n$cmdText$message";
        $message = $this->makeMessage($message);
        parent::__construct($message);
    }

    public function setFileCursor(FileCursor $fileCursor): self
    {
        return new self($this->command, $fileCursor);
    }
}
