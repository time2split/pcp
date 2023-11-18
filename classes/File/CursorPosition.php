<?php
namespace File;

final class CursorPosition
{

    private int $line;

    private int $linePos;

    private int $pos;

    public static function create(int $line, int $linePos, int $pos): CursorPosition
    {
        $ret = new CursorPosition();
        $ret->line = $line;
        $ret->linePos = $linePos;
        $ret->pos = $pos;
        return $ret;
    }

    public function decrement(): CursorPosition
    {
        return self::create($this->line, $this->linePos - 1, $this->pos - 1);
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getLinePos(): int
    {
        return $this->linePos;
    }

    public function getPos(): int
    {
        return $this->pos;
    }

    public function __toString(): string
    {
        return "p$this->pos:l$this->line:lp$this->linePos";
    }
}