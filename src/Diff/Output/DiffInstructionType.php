<?php

namespace Time2Split\PCP\Diff\Output;

enum DiffInstructionType
{
    case Insert;

    case Keep;

    case Drop;
}
