<?php
declare(strict_types = 1);
namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\Element\CElementType;

final class FunctionToFunction extends Instruction
{

    public function __construct(CElement $subject, Configuration $instruction, \SplFileInfo $sourceFile)
    {
        parent::__construct($subject, $instruction, $sourceFile);

        switch ($subject->getElementType()) {
            case CElementType::Function:
                break;
            default:
                throw new \Exception("Cannot generate a prototype from a {$subject->getElementType()->name} element");
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

    // ========================================================================
}