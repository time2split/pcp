<?php

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\DataFlow\ISubscriber;

interface IAction extends ISubscriber
{

    /**
     * Send a message to the Action that may (or may not) interpret it
     *
     * @param CElement $msg
     *            The C element message
     * @return array An array that may contains some new ActionCommand to send to each Action instances
     */
    public function onMessage(CElement $msg): MoreActions;

    public function onCommand(ActionCommand $command): MoreActions;

    function onPhase(Phase $phase, $data = null): void;

    function setConfig(Configuration $config);

    function hasMonopoly(): bool;

    function noExpandAtConfig(): bool;
}
