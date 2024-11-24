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

    public static function factory(Configuration $config): ActionFactory
    {
        return new class($config) implements ActionFactory {

            public function __construct(private Configuration $config) {}

            public function getActions(string $action): array
            {
                return match ($action) {
                    'process' => [
                        // new PCP\EchoAction($this->config),
                        new PCP\Generate($this->config),
                        new PCP\ForAction($this->config),
                        new PCP\BlockAction($this->config),
                        new PCP\ConfigAction($this->config)
                    ],
                    'clean' => [
                        new PCP\GenerateClean($this->config)
                    ],
                    default => []
                };
            }
        };
    }

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
