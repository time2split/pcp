<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElementType;

final class Factory
{
    public function __construct(private ReadingOneFile $readingFile) {}

    public function create(ActionCommand $command, CDeclaration $subject): Instruction
    {
        $instruction = $command->getArguments();
        $genPrototype = $instruction->isPresent('prototype');
        $genFunction = $instruction->isPresent('function');
        $genSomething = ($genPrototype xor $genFunction);

        if (!$genSomething) {
            throw new \AssertionError("Invalid Generate action: " . \print_r($instruction->toArray(), true));
        }
        $i = clone $instruction;

        if ($genPrototype) {
            unset($i['prototype']);
            return new Prototype($subject, $i);
        } elseif ($genFunction) {
            unset($i['function']);

            if ($subject->getElementType()[CElementType::Function])
                return new FunctionToFunction($subject, $i);

            throw new \AssertionError(sprintf("generate 'function': invalid C declaration subject '%s'", CElementType::stringOf($subject->getElementType())));
        }
    }

    public function createWithoutSubject(ActionCommand $command): Instruction
    {
        $instruction = $command->getArguments();

        if ($instruction->isPresent('code'))
            return new Code($instruction);

        throw new \AssertionError("Invalid instruction: " . \print_r($instruction->toArray(), true));
    }
}
