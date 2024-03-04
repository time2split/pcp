<?php
declare(strict_types = 1);
namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\C\CElements;

final class Code extends Instruction
{

    private string $code;

    public function __construct(Configuration $instruction, \SplFileInfo $sourceFile)
    {
        $noSubject = CElements::null();
        parent::__construct($noSubject, $instruction, $sourceFile);
        $this->code = $instruction['code'];
    }

    public function generate(): string
    {
        return $this->code;
    }

    public function getTargets(): array
    {
        $args = $this->getArguments();
        return (array) ($args['targets.code'] ?? $args['targets']);
    }
}