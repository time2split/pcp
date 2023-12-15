<?php
namespace C\Element;

use C\Element\ElementType;

trait ElementTypeTrait
{

    public function getElementType(): ElementType
    {
        return ElementType::of($this);
    }
}