<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\PCP\C\CDeclarationGroup;
use Time2Split\PCP\C\CReaderElement;

enum CElementType: string
{

    case Prototype = 'from.prototype';

    case Function = 'from.function';

    case CPPMacro = 'from.macro';

    public static function of(CReaderElement $element): self
    {
        if ($element instanceof CPPDefine)
            return self::CPPMacro;
        if ($element->getGroup() === CDeclarationGroup::definition)
            return self::Function;

        return self::Prototype;
    }
}