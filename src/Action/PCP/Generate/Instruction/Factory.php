<?php
namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\App;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\CElement;

final class Factory
{

    public function __construct(private ReadingOneFile $readingFile)
    {}

    public function create(CElement $subject, Configuration $instruction): Instruction
    {
        $i = clone $instruction;
        $kfirst = App::configFirstKey($i);

        if ($kfirst === 'prototype') {
            unset($i['prototype']);
            return new Prototype($subject, $i, $this->readingFile->fileInfo);
        }
        throw new \Exception(__class__ . " Invalid action '$kfirst': " . print_r($instruction, true));
    }
}