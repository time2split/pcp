<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\PCP\C\CReaderElement;

class CPPDirective extends CReaderElement
{
    use CElementTypeTrait;

    protected function __construct( //
    private readonly string $directive, //
    private readonly string $text, //
    private readonly array $fileCursors)
    {}

    final public static function create(string $directive, string $text, array $cursors)
    {
        return new self($directive, $text, $cursors);
    }

    final public function getFileCursors(): array
    {
        return $this->fileCursors;
    }

    final public function getText(): string
    {
        return $this->text;
    }

    final public function getDirective(): string
    {
        return $this->directive;
    }

    public function __toString()
    {
        return "#$this->directive $this->text /*({$this->fileCursors[0]})*/";
    }
}