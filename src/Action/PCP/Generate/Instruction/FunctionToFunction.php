<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CElementType;

final class FunctionToFunction extends Instruction
{
    public function __construct(CElement $subject, Configuration $instruction)
    {
        parent::__construct($subject, $instruction);
        $this->tags['function'] = true;
        $types = $subject->getElementType();

        if (!$types[CElementType::Function] || !$types[CElementType::Definition]) {
            $type = CElementType::stringOf($types);
            throw new \Exception("Cannot generate a function from a $type element");
        }
    }

    public function generate(): string
    {
        $subject = $this->getSubject();
        return Prototype::generatePrototype($subject, $this->getArguments()) . $subject['cstatement'];
    }

    public function getTargets(): array
    {
        $iconfig = $this->getArguments();
        return (array) ($iconfig['targets.function'] ?? $iconfig['targets']);
    }
}
