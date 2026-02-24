<?php

declare(strict_types=1);

namespace Time2Split\PCP\FS\Impl;

use Time2Split\Help\TriState;
use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\FS\FSFile;

class FSFileImpl
extends FSPathImpl
implements FSFile
{
    public function __construct(
        TriState $rooted,
        TriState $leafed,
        iterable $edges,
        ?bool $canonicalized = null,
    ) {
        if ($leafed !== TriState::Yes)
            throw new \InvalidArgumentException("(leafed) Param must be " . Tristate::Yes->name);

        parent::__construct($rooted, $leafed, $edges, $canonicalized);
    }

    #[\Override]
    public function inFS(bool $checkLeafType = true): bool
    {
        return $checkLeafType
            ? $this->info()->isFile()
            : \file_exists("$this");
    }

    #[\Override]
    public function setFile(string $fileName): FSFile
    {
        if ($fileName === '')
            throw new \InvalidArgumentException("(filenName) Cannot be empty");

        $parts = $this->toListOfElements();
        $parts[\array_key_last($parts)] = $fileName;
        return FS::fileOf($parts);
    }

    #[\Override]
    function getPath(): FSDir
    {
        $edges = $this->storage;
        \array_pop($edges);
        return new FSDirImpl($this->rooted, TriState::No, $edges);
    }
}
