<?php
namespace Process;

abstract class AbstractProcess
{

    protected \Data\TreeConfig $config;

    protected string $workingDir;

    protected function __construct(?string $workingDir = null)
    {
        if ($workingDir === null)
            $workingDir = \getcwd();

        $this->workingDir = $workingDir;
    }

    protected function process(\Data\TreeConfig $config)
    {
        $this->config = $config;
    }
}
