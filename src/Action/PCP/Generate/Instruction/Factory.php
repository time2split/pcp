<?php
namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\C\CElement;

final class Factory
{

    public function create(CElement $subject, Configuration $instruction): Instruction
    {
        $i = clone $instruction;
        $keys = $i->keys();
        $kfirst = $keys[0];

        if ($kfirst === 'prototype') {
            unset($i['prototype']);
            return Prototype::create($subject, $i);
        }
        throw new \Exception(__class__ . " Invalid action '$kfirst': " . print_r($instruction, true));
    }
}