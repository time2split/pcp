<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\PCP\C\CDeclarationGroup;
use Time2Split\PCP\C\CReaderElement;

enum CElementType: string
{

    case Prototype = 'prototype';

    case Function = 'function';

    case CPPMacro = 'cpp.macro';

    case CPPDirective = 'cpp.directive';

    public static function of(CReaderElement $element): self
    {
        if ($element instanceof CPPDefine)
            return self::CPPMacro;
        if ($element instanceof CPPDirective)
            return self::CPPDirective;
        if ($element instanceof CDeclaration) {

            if ($element->getGroup() === CDeclarationGroup::definition)
                return self::Function;

            return self::Prototype;
        }
        throw new \Error(__METHOD__ . "Unknown element type");
    }
}