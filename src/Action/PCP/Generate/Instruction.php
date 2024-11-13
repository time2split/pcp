<?php

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\Help\Set;
use Time2Split\Help\Sets;
use Time2Split\PCP\C\CElement;

abstract class Instruction
{

    public abstract function generate(): string;

    public abstract function getTargets(): array;

    // ========================================================================

    private CElement $subject;

    private Configuration $arguments;

    protected Set $tags;

    protected function __construct(CElement $subject, Configuration $arguments)
    {
        $this->subject = $subject;

        $i = clone $arguments;
        $tags = (array) ($arguments['tags'] ?? null);

        // Add the subject types as tags
        $stypes = $subject->getElementType();
        foreach ($stypes as $t)
            $tags[] = 'from.' . \strtolower($t->name);

        \sort($tags);
        $this->tags = Sets::arrayKeys();
        $this->tags->setMore(...$tags);
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

    public function getTags(): Set
    {
        return $this->tags;
    }
}
