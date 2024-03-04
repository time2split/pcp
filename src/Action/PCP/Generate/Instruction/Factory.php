<?php
declare(strict_types = 1);
namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\App;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\CDeclarationType;

final class Factory
{

    public function __construct(private ReadingOneFile $readingFile)
    {}

    public function create(CDeclaration $subject, Configuration $instruction): Instruction
    {
        $i = clone $instruction;
        $kfirst = App::configFirstKey($i);

        if ($kfirst === 'prototype') {
            unset($i['prototype']);
            return new Prototype($subject, $i, $this->readingFile->fileInfo);
        } elseif ($kfirst === 'function') {
            unset($i['function']);

            if ($subject->getType() === CDeclarationType::tfunction)
                return new FunctionToFunction($subject, $i, $this->readingFile->fileInfo);

            throw new \Exception(sprintf("generate 'function': invalid C declaration subject '%s'", $subject->getType()->name));
        }
        throw new \Exception("Invalid action '$kfirst': " . \print_r($instruction->toArray(), true));
    }
}