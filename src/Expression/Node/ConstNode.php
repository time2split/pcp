<?php

declare(strict_types=1);

namespace Time2Split\PCP\Expression\Node;

use Time2Split\Config\Configuration;

abstract class ConstNode implements Node
{
    public abstract function getValue(): mixed;

    final public function get(Configuration $subject): mixed
    {
        return $this->getValue();
    }

    public function __toString()
    {
        return (string)$this->getValue();
    }
}
