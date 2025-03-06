<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\Config\Interpolation;
use Time2Split\Config\Interpolator;
use Time2Split\Help\Classes\NotInstanciable;

final class Actions
{
    use NotInstanciable;

    public static function checkInterpolations(Configuration $config, Interpolator $interpolator = null, Interpolation ...$expressions): bool
    {
        foreach ($expressions as $expr) {
            $check = ($interpolator ?? $config->getInterpolator())->execute($expr->compilation, $config);

            if (!$check)
                return false;
        }
        return true;
    }
}
