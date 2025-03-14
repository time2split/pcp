<?php

declare(strict_types=1);

namespace Time2Split\PCP\Diff\Output;

abstract class DiffInstruction
{
    public function __construct(
        public readonly DiffInstructionType $type,
        public readonly mixed $item
    ) {}
}
