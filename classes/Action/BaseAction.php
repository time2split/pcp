<?php
namespace Action;

abstract class BaseAction extends \DataFlow\BaseSubscriber implements IAction
{

    protected \Data\TreeConfig $config;

    public function __construct(\Data\TreeConfig $config)
    {
        $this->config = $config;
    }

    public final function onNext($data): void
    {
        $this->onMessage($data);
    }

    public function onPhase(Phase $phase, $data = null): void
    {}
}