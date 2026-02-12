<?php

declare(strict_types=1);

namespace Time2Split\PCP\FS\Impl;

use Time2Split\Help\TriState;
use Time2Split\PCP\FS\FSDir;

class FSDirImpl
extends FSPathImpl
implements FSDir
{
    public function __construct(
        TriState $rooted,
        TriState $leafed,
        iterable $edges,
        ?bool $canonicalized = null,
    ) {
        if ($leafed !== TriState::No)
            throw new \InvalidArgumentException("(leafed) Param must be " . Tristate::No);

        parent::__construct($rooted, $leafed, $edges, $canonicalized);
    }

    #[\Override]
    public function inFS(bool $checkLeafType = true): bool
    {
        return $checkLeafType
            ? $this->info()->isDir()
            : \file_exists("$this");
    }
}
