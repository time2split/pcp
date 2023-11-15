<?php
namespace Action;

interface IActionMessage
{

    /**
     * Send the message to an action.
     *
     * @param IAction $action
     *            The action subject
     * @return bool true if the message is delivered
     */
    public function sendTo(IAction $action): bool;
}