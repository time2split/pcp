<?php
namespace Time2Split\PCP\Action;

use Time2Split\PCP\C\Element\Container;
use Time2Split\PCP\DataFlow\ISubscriber;

interface IAction extends ISubscriber
{

    function onMessage(Container $msg): void;

    function onPhase(Phase $phase, $data = null): void;
}