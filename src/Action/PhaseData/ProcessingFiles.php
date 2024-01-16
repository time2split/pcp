<?php
namespace Time2Split\PCP\Action\PhaseData;

use Time2Split\Config\IConfig;

final class ProcessingFiles
{

    public IConfig $config;

    private function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    public static function create(IConfig $config): ReadingOneFile
    {
        return new ProcessingFiles($config);
    }
}