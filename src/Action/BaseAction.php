<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\Help\Arrays;
use Time2Split\Help\IO;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\DataFlow\BaseSubscriber;

abstract class BaseAction extends BaseSubscriber implements IAction
{

    protected Configuration $config;

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

    protected final function getExprIdentifier(ActionCommand $command, string $actionName): mixed
    {
        $args = $command->getArguments();
        $expr = $args->getOptional('@expr');

        if (!$expr->isPresent())
            throw new \Exception("A '$actionName' action must have an identifier, have:\n$command");

        $expr = Arrays::ensureArray($expr->get());

        if (\count($expr) > 1)
            throw new \Exception("A '$actionName' action can only have one identifier, have:\n$command");

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

    public function onCommand(ActionCommand $command): MoreActions
    {
        return MoreActions::empty();
    }

    public function onMessage(CElement $msg): MoreActions
    {
        return MoreActions::empty();
    }

    public function onPhase(Phase $phase, $data = null): void {}
}
