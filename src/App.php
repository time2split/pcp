<?php

namespace Time2Split\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Config\TreeConfigurationBuilder;
use Time2Split\Help\Iterables;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\Expression\Expressions;
use Time2Split\PCP\File\StreamInsertion;
use Time2Split\PCP\File\_internal\StreamInsertionImpl;

final class App
{
    use NotInstanciable;

    public static function emptyConfiguration(): Configuration
    {
        return self::getConfigBuilder()->build();
    }

    public static function configuration(array $config): Configuration
    {
        return self::getConfigBuilder()->mergeTree($config)->build();
    }

    public static function getConfigBuilder(): TreeConfigurationBuilder
    {
        return Configurations::builder()->setKeyDelimiter('.')->setInterpolator(Expressions::interpolator());
    }

    public static function fileInsertion(string $file, string $buffFile): StreamInsertion
    {
        copy($file, $buffFile);
        return StreamInsertionImpl::fromFilePath($buffFile, $file);
    }

    // ========================================================================
    public static function configFirstKey(Configuration $config, $default = null): mixed
    {
        return Iterables::firstKey($config, $default);
    }

    public static function configFirstValue(Configuration $config, $default = null): mixed
    {
        return Iterables::firstValue($config, $default);
    }

    public static function configFirstKeyValue(Configuration $config, $default = null): mixed
    {
        return Iterables::first($config, $default);
    }

    public static function configShift(Configuration $config, int $nb = 1): Configuration
    {
        $ret = clone $config;

        if ($nb === 0)
            return $ret;

        if ($nb < 0)
            throw new \ValueError(__FUNCTION__ . " \$nb must be a positive or a zero integer");

        $keys = Iterables::keys($config);

        foreach ($keys as $k) {

            if (!isset($k))
                break;

            unset($ret[$k]);

            if (--$nb === 0)
                return $ret;
        }
    }
}
