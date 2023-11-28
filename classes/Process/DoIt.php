<?php
namespace Process;

class DoIt extends AbstractProcess
{

    public function __construct(?string $workingDir = null)
    {
        parent::__construct($workingDir);
    }






    private function process_c()
    {
        $pcp = new \C\PCP();
        $pcp->process($this->config);
    }

    public function process(\Data\IConfig $config)
    {
        parent::process($config);
        $this->process_c();
    }
}
