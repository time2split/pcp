<?php
namespace DataFlow;

interface IPublisher
{

    /**
     * Subscribe to another action to request some IActionMessage
     *
     * @param IAction $action
     */
    function subscribe(ISubscriber $s): void;
}