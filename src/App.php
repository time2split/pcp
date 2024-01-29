<?php
namespace Time2Split\PCP;

use Time2Split\Config\Configuration;
use Time2Split\Config\TreeConfigBuilder;
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

    public static function getConfigBuilder(): TreeConfigBuilder
    {
        return TreeConfigBuilder::builder()->setDelimiter('.')->setInterpolator(Expressions::interpolator());
    }

    public static function fileInsertion(string $file, string $buffFile): StreamInsertion
    {
        copy($file, $buffFile);
        return StreamInsertionImpl::fromFilePath($buffFile, $file);
    }
}