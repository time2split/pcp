<?php
namespace Time2Split\PCP\Action\PCP\Generate;

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

        $finfos = new \SplFileInfo($path);
        return $this->targets[$path] = self::createTarget($finfos);
    }

    // ========================================================================
    private static function createTarget(\SplFileInfo $finfo): Target
    {
        return new class($finfo) implements Target {

            private array $areas = [];

            function __construct(private readonly \SplFileInfo $finfo)
            {}

            public function getAreas(): array
            {}

            public function getFileInfo(): \SplFileInfo
            {
                return $this->finfo;
            }
        };
    }
}