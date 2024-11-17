<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\CActionSubject;
use Time2Split\PCP\C\CElement;

abstract class Instruction extends CActionSubject
{

    public abstract function generate(): string;

    public abstract function getTargets(): array;

    // ========================================================================

    private Configuration $arguments;

    protected function __construct(CElement $subject, Configuration $arguments)
    {
        parent::__construct($subject);

        $pretags = (array) ($arguments['tags'] ?? null);
        $this->tags->setMore(...$pretags);

        $i = clone $arguments;
        unset($i['tags']);
        $this->arguments = $i;
    }

    public function getArguments(): Configuration
    {
        return $this->arguments;
    }
}
