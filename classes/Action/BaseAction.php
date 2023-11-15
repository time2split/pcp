<?php
namespace Action;

abstract class BaseAction implements IAction
{

    public final function onNext($data): void
    {
        $this->onMessage($data);
    }

    public function onPhase(Phase $phase, $data = null): void
    {}
}