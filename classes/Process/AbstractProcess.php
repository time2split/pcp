<?php
namespace Process;

abstract class AbstractProcess
{

    protected \Data\IConfig $config;

    protected string $workingDir;

    protected function __construct(?string $workingDir = null)
    {
        if ($workingDir === null)
            $workingDir = \getcwd();

        $this->workingDir = $workingDir;
    }

    protected function process(\Data\IConfig $config)
    {
        $this->config = $config;
    }
}
