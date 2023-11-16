<?php
namespace Action\PCP;

class Conf extends \Action\BaseAction
{

    public function onMessage(\Action\IActionMessage $msg): void
    {
        if ($msg instanceof \Action\Instruction && $msg->getCommand() === 'conf') {
            $args = $msg->getArguments();

            list ($list, $array) = \Help\Arrays::partition($args, 'is_int', ARRAY_FILTER_USE_KEY);

            foreach ($array as $k => $v)
                $this->config[$k] = $v;

            $k = null;
            foreach ($list as $v) {
                if ($k === null)
                    $this->config[$k = $v] = null;
                else {
                    $this->config[$k] = $v;
                    $k = null;
                }
            }
        }
    }
}