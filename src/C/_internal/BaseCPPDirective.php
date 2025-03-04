<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\_internal;

use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\File\Section;

abstract class BaseCPPDirective implements CPPDirective
{
    public function __construct(
        private readonly string $directive,
        private readonly string $text,
        private readonly Section $fileSection
    ) {}

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
