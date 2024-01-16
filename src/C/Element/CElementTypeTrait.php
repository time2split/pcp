<?php
namespace Time2Split\PCP\C\Element;

trait CElementTypeTrait
{

    public function getElementType(): CElementType
    {
        return CElementType::of($this);
    }
}