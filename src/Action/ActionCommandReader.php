<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\PCP\_internal\AbstractReader;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\App;

final class ActionCommandReader extends AbstractReader
{

    // ========================================================================

    public function next(): ?ActionCommand
    {
        $command = $this->nextWord();

        if ('' === $command) {
            $c = $this->fnav->getc();

            if ($c !== false)
                throw new \Exception("Waiting for a command name");
            else
                return null;
        }
        $this->skipSpaces();
        $text = '';

        while (true) {
            $c = $this->fgetc();

            if (false === $c  || $c === "\n")
                break;
            if ($c === "\\\n")
                $c = ' ';

            $text .= $c;
        }
        $arguments =  App::textToParameters($text);
        return ActionCommand::create($command, $arguments);
    }
}
