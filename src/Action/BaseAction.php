<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\Help\Arrays;
use Time2Split\Help\IO;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;
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

    public function noExpandAtConfig(): bool
    {
        return false;
    }

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public final function onNext($data): void
    {
        $this->onMessage($data);
    }

    protected final function getExprIdentifier(PCPPragma $pcpPragma, string $actionName): mixed
    {
        $args = $pcpPragma->getArguments();
        $expr = $args->getOptional('@expr');

        if (!$expr->isPresent())
            throw new \Exception("A '$actionName' action must have an identifier, have:\n$pcpPragma");

        $expr = Arrays::ensureArray($expr->get());

        if (\count($expr) > 1)
            throw new \Exception("A '$actionName' action can only have one identifier, have:\n$pcpPragma");

        return $expr[0];
    }

    protected final function goWorkingDir(string $subDir = ''): void
    {
        if (\strlen($subDir) > 0 && $subDir[0] !== '/')
            $subDir = "/$subDir";

        $dir = $this->config['pcp.dir'];
        $wd = $dir . $subDir;

        if (0 === \strlen($wd))
            $wd = '.';

        IO::wdPush($wd);
    }

    protected final function outWorkingDir(): void
    {
        IO::wdPop();
    }

    public function onMessage(CContainer $msg): IMoreActions
    {
        return MoreActions::empty();
    }

    public function onPhase(Phase $phase, $data = null): void {}
}
