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
        $it = new \AppendIterator();
        foreach ($this->config['paths'] as $path) {
            $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME);
            $dirIterator = new \RecursiveIteratorIterator($dirIterator);
            $dirIterator = new \RegexIterator($dirIterator, "/^.+\.[hc]$/");
            $it->append($dirIterator);
        }
        $pcp = new \C\PCP();
        $pcp->process($this->config, $it);
    }

    public function process(\Data\TreeConfig $config)
    {
        parent::process($config);
        $this->process_c();
    }
}
