<?php
namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\C\Element\Container;

class EchoAction extends BaseAction
{

    public function onSubscribe(): void
    {
        error_dump(__class__ . " onSubscribe()");
    }

    public function onMessage(Container $msg): void
    {
        error_dump(__class__ . " onMessage()");
        error_dump($msg);
    }

    public function onPhase(Phase $phase, $data = null): void
    {
        error_dump(__class__ . " onPhase() $phase", $data);
    }
}