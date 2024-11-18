<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\For;

use Time2Split\Config\Configuration;
use Time2Split\Config\Interpolation;
use Time2Split\Config\Interpolator;
use Time2Split\PCP\Action\Actions;

final class Cond
{

    public array $instructions = [];

    private readonly array $conditions;

    public function __construct(
        public Configuration $config,
        Interpolation ...$conditions
    ) {
        $this->conditions = $conditions;
    }

    public function check(Configuration $config, Interpolator $intp = null): bool
    {
        return Actions::checkInterpolations($config, $intp ?? $config->getInterpolator(), ...$this->conditions);
    }
}
