<?php
namespace Time2Split\PCP\Action\PhaseData;

use Time2Split\Config\Configuration;

final class ProcessingFiles
{

    public Configuration $config;

    private function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public static function create(Configuration $config): ReadingOneFile
    {
        return new ProcessingFiles($config);
    }
}