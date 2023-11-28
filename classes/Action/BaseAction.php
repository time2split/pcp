<?php
namespace Action;

abstract class BaseAction extends \DataFlow\BaseSubscriber implements IAction
{

    protected \Data\IConfig $config;

    public function __construct(\Data\IConfig $config)
    {
        $this->config = $config;
    }

    public final function onNext($data): void
    {
        $this->onMessage($data);
    }

    public final function goWorkingDir(string $subDir = ''): void
    {
        if (\strlen($subDir) > 0 && $subDir[0] !== '/')
            $subDir = "/$subDir";

        \Help\IO::wdPush($this->config['cpp.wd'] . $subDir);
    }

    public final function outWorkingDir(): void
    {
        \Help\IO::wdPop();
    }

    public function onPhase(Phase $phase, $data = null): void
    {}
}