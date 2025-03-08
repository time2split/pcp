<?php

declare(strict_types=1);

namespace Time2Split\PCP\Expression;

use Time2Split\PCP\Expression\Node\Node;

/**
 * @internal
 */
final class FilterCallable
{
    public $callable;

    public array $arguments;

    public function __construct(
        callable $callable,
        Node ...$arguments
    ) {
        $this->arguments = $arguments;
        $this->callable = $callable;
    }
}
