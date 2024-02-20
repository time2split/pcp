<?php
namespace Time2Split\PCP\File;

final class Section
{

    public function __construct(public readonly CursorPosition $begin, public readonly CursorPosition $end)
    {}

    public static function createPoint(CursorPosition $pos): self
    {
        return new self($pos, $pos);
    }

    public function getCursorPositions(): array
    {
        return [
            $this->begin,
            $this->end
        ];
    }

    public function __toString(): string
    {
        return "begin:$this->begin, end:$this->end";
    }
}