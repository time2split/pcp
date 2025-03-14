<?php

declare(strict_types=1);

namespace Time2Split\PCP\Diff\Output;

use Time2Split\Help\Classes\NotInstanciable;

final class DiffInstructions
{
    use NotInstanciable;

    public static function createInstruction(DiffInstructionType $type, mixed $item): DiffInstruction
    {
        return new class($type, $item) extends DiffInstruction {};
    }
}
