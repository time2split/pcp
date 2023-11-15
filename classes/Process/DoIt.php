<?php
namespace Process;

class DoIt extends AbstractProcess
{
	private array $conf = [];

    public function __construct(?string $workingDir = null)
    {
        parent::__construct($workingDir);
    }

    private function process_php(array $files)
    {
        foreach ($files as $file) {
            $newFileName = \substr(\basename($file), 0, - 4);
            $path = \dirname($file);
            $newFile = "$path/$newFileName";

            if (\file_exists($newFile) && \Help\IO::olderThan($newFile, $file)) {

                if (! $this->conf['debug'])
                    continue;

                echo "$newFile normally passed\n";
            }
            echo "Generate $newFile\n";
            \ob_start();

            if (false === include "$file")
                exit(1);

            \file_put_contents($newFile, \ob_get_clean());
        }
    }

    private function process_c(array $files)
    {
        $confPath = $this->conf['pragmas_fileConfig'];
        $pragmaConfig = loadConfig($confPath);

        foreach ($files as $file) {
            $pragma = new \C\PCP($file, $pragmaConfig);
            $pragma->process();
        }
        $pragmaConfig['cleaned'] = false;
        saveConfig($confPath, $pragmaConfig);
    }

    public function process(array $conf)
    {
        $this->conf = $conf;
        $this->process_php(getFiles_php($conf));
        $this->process_c(getFiles_c($conf));
    }
}
