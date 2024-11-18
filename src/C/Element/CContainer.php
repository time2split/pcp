<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Optional;
use Time2Split\PCP\C\CElement;

final class CContainer
{

    private function __construct(
        private readonly Optional $cppDirective,
        private readonly Optional $declaration
    ) {}

    public static function of(CPPDirective|CDeclaration $element): self
    {
        if ($element instanceof CPPDirective)
            return self::ofCPPDirective($element);

        return self::ofDeclaration($element);
    }

    public static function ofCPPDirective(CPPDirective $macro): self
    {
        return new self(Optional::of($macro), Optional::empty());
    }

    public static function ofDeclaration(CDeclaration $declaration): self
    {
        return new self(Optional::empty(), Optional::of($declaration));
    }

    // ========================================================================
    public final function isCPPDirective(): bool
    {
        return $this->cppDirective->isPresent();
    }

    public final function isMacroDefinition(): bool
    {
        return $this->isCPPDirective() && $this->getCppDirective() instanceof CPPDefine;
    }

    public final function isPCPPragma(): bool
    {
        return $this->isCPPDirective() && $this->getCppDirective() instanceof PCPPragma;
    }

    public final function isDeclaration(): bool
    {
        return $this->declaration->isPresent();
    }

    // ========================================================================
    public final function getCppDirective(): CPPDirective
    {
        return $this->cppDirective->get();
    }

    public final function getMacroDefinition(): CPPDefine
    {
        return $this->cppDirective->get();
    }

    public final function getPCPPragma(): PCPPragma
    {
        return $this->cppDirective->get();
    }

    public final function getDeclaration(): CDeclaration
    {
        return $this->declaration->get();
    }

    public final function getCElement(): CElement
    {
        if ($this->cppDirective->isPresent())
            return $this->cppDirective->get();

        return $this->declaration->get();
    }
}
