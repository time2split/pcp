<?php
namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\Help\IO;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\DataFlow\BaseSubscriber;

abstract class BaseAction extends BaseSubscriber implements IAction
{

    protected Configuration $config;

    public function __construct(Configuration $config)
    {
        $this->config = $this->decorateConfig($config);
    }

    public final function onNext($data): void
    {
        $this->onMessage($data);
    }

    public final function goWorkingDir(string $subDir = ''): void
    {
        if (\strlen($subDir) > 0 && $subDir[0] !== '/')
            $subDir = "/$subDir";

        IO::wdPush($this->config['cpp.wd'] . $subDir);
    }

    public final function decorateConfig(Configuration $config): Configuration
    {
        return $config;
        // Deactivated; TODO: set env config
//         return InterpolatedConfig::from($config, [
//             'env' => getenv(...)
//         ]);
    }

    public final function outWorkingDir(): void
    {
        IO::wdPop();
    }

    public function onMessage(CContainer $msg): void
    {}

    public function onPhase(Phase $phase, $data = null): void
    {}
}