<?php
namespace C\Element;

use C\ReaderElement;

enum ElementType: string
{

    case Prototype = 'from.prototype';

    case Function = 'from.function';

    case Macro = 'from.macro';

    public static function of(ReaderElement $element): self
    {
        if ($element instanceof Macro)
            return self::Macro;
        if ($element->getGroup() === \C\DeclarationGroup::definition)
            return self::Function;

        return self::Prototype;
    }
}