<?php

declare(strict_types=1);

namespace Time2Split\PCP\C;

use Time2Split\Help\Set;

abstract class CReaderElement extends \ArrayObject implements CElement
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
