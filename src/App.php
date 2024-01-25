<?php
namespace Time2Split\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\TreeConfigBuilder;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\Expression\Expressions;

final class App
{
    use NotInstanciable;

    public static function emptyConfiguration(): Configuration
    {
        return self::getConfigBuilder()->build();
    }

    public static function getConfigBuilder(): TreeConfigBuilder
    {
        return TreeConfigBuilder::builder()->setDelimiter('.')->setInterpolator(Expressions::interpolator());
    }
}