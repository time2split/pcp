<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Set;
use Time2Split\PCP\C\CReaderElement;
use Time2Split\PCP\File\Section;

class CPPDirective extends CReaderElement
{
    protected function __construct(
        private readonly string $directive,
        private readonly string $text,
        private readonly Section $fileSection
    ) {}

    final public static function create(string $directive, string $text, Section $cursors)
    {
        return new self($directive, $text, $cursors);
    }

    public function getElementType(): Set
    {
        return CElementType::of(CElementType::CPP);
    }

    final public function getFileSection(): Section
    {
        return $this->fileSection;
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
        return "#$this->directive $this->text /*($this->fileSection)*/";
    }
}
