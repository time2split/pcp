<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\_internal;

use Time2Split\Help\Set;
use Time2Split\PCP\C\Element\CElement;

abstract class AbstractCElement extends \ArrayObject implements CElement
{
    private Set $elementType;

    protected function __construct(array $elements)
    {
        parent::__construct($elements);
        $this->elementType = $elements['type'];
        unset($this['type']);
    }

    public function getElementType(): Set
    {
        return $this->elementType;
    }
}
