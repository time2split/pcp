<?php
namespace Action\PCP;

class Conf extends \Action\BaseAction
{

    public function onMessage(\Action\IActionMessage $msg): void
    {
        if ($msg instanceof \Action\Instruction && $msg->getCommand() === 'conf') {
            $args = $msg->getArguments();
            $this->config->arrayMergeRecursive($args);
        }
    }
}