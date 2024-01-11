<?php
namespace Action;

use C\Element\Container;

interface IAction extends \DataFlow\ISubscriber
{

    /**
     * Deliver a message to this action.
     *
     * @param ActionMessage $msg
     *            The message to deliver
     * @return bool true if the message is delivered.
     */
    function onMessage(Container $msg): void;

    function onPhase(Phase $phase, $data = null): void;
}