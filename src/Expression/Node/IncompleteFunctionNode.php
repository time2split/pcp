<?php

declare(strict_types=1);

namespace Time2Split\PCP\Expression\Node;

use Time2Split\Config\Configuration;

final class IncompleteFunctionNode implements Node
{
    public readonly string $name;

    public readonly array $arguments;

    public final function __construct(
        string $name,
        Node ...$arguments,
    ) {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function get(Configuration $subject): mixed
    {
        throw new \AssertionError("A function node is not intended to be used alone");
    }

    public function __toString()
    {
        $params = \array_map(fn($i) => (string)$i, $this->arguments);
        $params = \implode(', ', $params);
        return "$this->name($params)";
    }
}
