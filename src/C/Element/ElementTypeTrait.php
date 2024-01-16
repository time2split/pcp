<?php
namespace Time2Split\PCP\C\Element;

trait ElementTypeTrait
{

    public function getElementType(): ElementType
    {
        return ElementType::of($this);
    }
}