<?php
namespace Action\PCP\Generate\Instruction;

use Data\IConfig;
use C\Element;

final class Factory
{

    public function create(Element $subject, IConfig $instruction): \Action\PCP\Generate\Instruction
    {
        $i = clone $instruction;
        $ret = null;
        $keys = $i->keys();
        $kfirst = $keys[0];

        if ($kfirst === 'prototype') {
            unset($i['prototype']);
            return Prototype::create($subject, $i);
        }
        throw new \Exception(__class__ . " Invalid action '$kfirst': " . print_r($instruction, true));
    }
}