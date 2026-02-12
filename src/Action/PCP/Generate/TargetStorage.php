<?php

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\PCP\FS\FS;
use Time2Split\PCP\FS\FSFile;

final class TargetStorage
{

    private array $targets = [];

    public function __construct()
    {
        $this->targets = [];
    }

    // ========================================================================
    public function getTarget(string $path): Target
    {
        $target = $this->targets[$path] ?? null;

        if (isset($target))
            return $target;

        $fsFile = FS::fileOf($path);
        return $this->targets[$path] = self::createTarget($fsFile);
    }

    // ========================================================================
    private static function createTarget(FSFile $fsFile): Target
    {
        // assert($fsFile->rooted() === TriState::No);

        return new class($fsFile) implements Target {

            function __construct(
                private readonly FSFile $fsFile,
            ) {}

            public function getFSFile(): FSFile
            {
                return $this->fsFile;
            }
        };
    }
}
