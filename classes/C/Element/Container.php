<?php
namespace C\Element;

use Help\Optional;

final class Container
{

    private Optional $macro;

    private Optional $declaration;

    private function __construct()
    {
        $this->macro = Optional::empty();
        $this->declaration = Optional::empty();
    }

    public static function of(Macro|Declaration $element): self
    {
        if ($element instanceof Macro)
            return self::ofMacro($element);

        return self::ofDeclaration($element);
    }

    public static function ofMacro(Macro $macro): self
    {
        $ret = new self();
        $ret->macro = Optional::of($macro);
        return $ret;
    }

    public static function ofDeclaration(Declaration $declaration): self
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

    public final function getMacro(): Macro
    {
        return $this->macro->get();
    }

    public final function getDeclaration(): Declaration
    {
        return $this->declaration->get();
    }
}