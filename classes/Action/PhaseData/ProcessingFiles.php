<?php
namespace Action\PhaseData;

final class ProcessingFiles
{

    public \Data\TreeConfig $config;

    private function __construct(\Data\TreeConfig $config)
    {
        $this->config = $config;
    }

    public static function create(\Data\TreeConfig $config): ReadingOneFile
    {
        return new ProcessingFiles($config);
    }
}