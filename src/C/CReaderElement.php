<?php
declare(strict_types = 1);
namespace Time2Split\PCP\C;

abstract class CReaderElement extends \ArrayObject implements CElement
{

    protected function __construct(array $elements)
    {
        parent::__construct($elements);
    }
}