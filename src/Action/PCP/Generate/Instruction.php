<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\PCP\C\CElement;

abstract class Instruction
{

    public abstract function generate(): string;

    public abstract function getTargets(): array;

    // ========================================================================
    private CElement $subject;

    private Configuration $instruction;

    private array $tags;

    protected function __construct(CElement $subject, Configuration $instruction)
    {
        $this->subject = $subject;

        $i = clone $instruction;
        $stype = $subject->getElementType();

        $tags = (array) ($instruction['tags'] ?? null);
        $tags[] = $stype->value;
        \sort($tags);
        $this->tags = $tags;
        unset($i['tags']);

        $this->instruction = $i;
    }

    public function getSubject(): CElement
    {
        return $this->subject;
    }

    public function getInstruction(): Configuration
    {
        return $this->instruction;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
