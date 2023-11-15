<?php
namespace Action\PCP;

class EchoAction extends \Action\BaseAction
{

    public function onSubscribe(): void
    {
        error_dump(__class__ . " onSubscribe()");
    }

    public function onMessage(\Action\IActionMessage $msg): void
    {
        error_dump(__class__ . " onMessage()");
        error_dump($msg);
    }

    public function onPhase(\Action\Phase $phase, $data = null): void
    {
        error_dump(__class__ . " onPhase() $phase", $data);
    }
}