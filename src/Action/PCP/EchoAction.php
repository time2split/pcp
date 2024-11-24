<?php

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\IMoreActions;
use Time2Split\PCP\Action\MoreActions;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\C\Element\CContainer;

class EchoAction extends BaseAction
{

    public function onSubscribe(): void
    {
        error_dump(__class__ . " onSubscribe()");
    }

    public function onMessage(CContainer $msg): IMoreActions
    {
        error_dump(__class__ . " onMessage()");
        error_dump($msg);
        return MoreActions::empty();
    }

    public function onPhase(Phase $phase, $data = null): void
    {
        error_dump(__class__ . " onPhase() $phase", $data);
    }
}
