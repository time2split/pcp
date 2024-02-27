<?php
declare(strict_types = 1);
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
        $this->setConfig($config);
    }

    public function hasMonopoly(): bool
    {
        return false;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public final function onNext($data): void
    {
        $this->onMessage($data);
    }

    protected final function goWorkingDir(string $subDir = ''): void
    {
        if (\strlen($subDir) > 0 && $subDir[0] !== '/')
            $subDir = "/$subDir";

        IO::wdPush($this->config['pcp.dir'] . $subDir);
    }

    protected final function outWorkingDir(): void
    {
        IO::wdPop();
    }

    public function onMessage(CContainer $msg): array
    {
        return [];
    }

    public function onPhase(Phase $phase, $data = null): void
    {}
}