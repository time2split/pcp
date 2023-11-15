<?php
namespace Action;

interface IAction extends \DataFlow\ISubscriber
{

    /**
     * Deliver a message to this action.
     *
     * @param ActionMessage $msg
     *            The message to deliver
     * @return bool true if the message is delivered.
     */
    function onMessage(IActionMessage $msg): void;

    function onPhase(Phase $phase, $data = null): void;
}