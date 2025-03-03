<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\_internal;

use Time2Split\Help\Set;
use Time2Split\PCP\C\Element\CElementType;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\File\Section;

class BaseCPPDirective implements CPPDirective
{
    public function __construct(
        private readonly string $directive,
        private readonly string $text,
        private readonly Section $fileSection
    ) {}

    public function getElementType(): Set
    {
        return CElementType::ofCPP();
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
