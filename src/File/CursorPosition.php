<?php
namespace Time2Split\PCP\File;

final class CursorPosition
{

    public function __construct(public readonly int $line, public readonly int $linePos, public readonly int $pos)
    {}

    public function decrement(): CursorPosition
    {
        return new self($this->line, $this->linePos - 1, $this->pos - 1);
    }

    public function __toString(): string
    {
        return "p$this->pos:l$this->line:lp$this->linePos";
    }
}