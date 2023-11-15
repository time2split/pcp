<?php
namespace DataFlow;

interface ISubscriber
{

    /**
     * Data notification send by the publisher
     *
     * @param IAction $action
     */
    function onNext($data): void;

    /**
     * Notification send on subscription to a Publisher.
     *
     * @param IAction $action
     */
    function onSubscribe(): void;
}