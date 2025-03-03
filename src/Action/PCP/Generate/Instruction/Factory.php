<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElementType;

final class Factory
{

    public function __construct(private ReadingOneFile $readingFile) {}

    public function create(CDeclaration $subject, Configuration $instruction): Instruction
    {
        $genPrototype = $instruction->isPresent('prototype');
        $genFunction = $instruction->isPresent('function');
        $genSomething = ($genPrototype xor $genFunction);

        if (!$genSomething) {
            throw new \Exception("Invalid Generate action: " . \print_r($instruction->toArray(), true));
        }
        $i = clone $instruction;

        if ($genPrototype) {
            unset($i['prototype']);
            return new Prototype($subject, $i);
        } elseif ($genFunction) {
            unset($i['function']);

            if ($subject->getElementType()[CElementType::Function])
                return new FunctionToFunction($subject, $i);

            throw new \Exception(sprintf("generate 'function': invalid C declaration subject '%s'", CElementType::stringOf($subject->getElementType())));
        }
    }

    public function createWithoutSubject(Configuration $instruction): Instruction
    {
        if (isset($instruction['code']))
            return new Code($instruction, $this->readingFile->fileInfo);

        throw new \Exception("Invalid instruction: " . \print_r($instruction->toArray(), true));
    }
}
