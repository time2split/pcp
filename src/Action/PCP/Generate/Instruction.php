<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\PCP\C\CElement;

abstract class Instruction
{

    public abstract function generate(): string;

    public abstract function getTargets(): array;

    // ========================================================================
    private array $targets;

    private CElement $subject;

    private Configuration $arguments;

    private array $tags;

    protected function __construct(CElement $subject, Configuration $arguments, \SplFileInfo $sourceFile)
    {
        $this->subject = $subject;

        $i = clone $arguments;
        $stype = $subject->getElementType();

        $tags = (array) ($arguments['tags'] ?? null);
        $tags[] = $stype->value;
        \sort($tags);
        $this->tags = $tags;
        unset($i['tags']);

        $this->arguments = $i;
    }

    public function getSubject(): CElement
    {
        return $this->subject;
    }

    public function getArguments(): Configuration
    {
        return $this->arguments;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
