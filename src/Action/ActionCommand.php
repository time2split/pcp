<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;

final class ActionCommand
{
    protected function __construct(
        private readonly string $name,
        private readonly Configuration $arguments,
    ) {}


    final public static function create(string $name, Configuration $arguments)
    {
        return new self($name, $arguments);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): Configuration
    {
        return $this->arguments;
    }
}
