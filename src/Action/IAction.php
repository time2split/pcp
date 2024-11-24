<?php

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\DataFlow\ISubscriber;

interface IAction extends ISubscriber
{

    /**
     * Send a message to the Action that may (or may not) interpret it
     *
     * @param CContainer $msg
     *            The C element message
     * @return array An array that may contains some new PCPPragma instructions to send to each Action instances
     */
    function onMessage(CContainer $msg): IMoreActions;

    function onPhase(Phase $phase, $data = null): void;

    function setConfig(Configuration $config);

    function hasMonopoly(): bool;

    function noExpandAtConfig(): bool;
}
