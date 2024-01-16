<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Optional;

final class CContainer
{

    private Optional $macro;

    private Optional $declaration;

    private function __construct()
    {
        $this->macro = Optional::empty();
        $this->declaration = Optional::empty();
    }

    public static function of(CMacro|CDeclaration $element): self
    {
        if ($element instanceof CMacro)
            return self::ofMacro($element);

        return self::ofDeclaration($element);
    }

    public static function ofMacro(CMacro $macro): self
    {
        $ret = new self();
        $ret->macro = Optional::of($macro);
        return $ret;
    }

    public static function ofDeclaration(CDeclaration $declaration): self
    {
        $ret = new self();
        $ret->declaration = Optional::of($declaration);
        return $ret;
    }

    public final function isMacro(): bool
    {
        return $this->macro->isPresent();
    }

    public final function isDeclaration(): bool
    {
        return $this->declaration->isPresent();
    }

    public final function getMacro(): CMacro
    {
        return $this->macro->get();
    }

    public final function getDeclaration(): CDeclaration
    {
        return $this->declaration->get();
    }
}