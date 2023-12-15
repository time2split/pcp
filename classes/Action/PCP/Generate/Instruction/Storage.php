<?php
namespace Action\PCP\Generate\Instruction;

use Action\PCP\Generate\Instruction;
use Data\IConfig;
use Action\PhaseData\ReadingOneFile;

final class Storage
{

    private int $groupId = 0;

    private IConfig $config;

    private array $storage;

    public function __construct(IConfig $mainConfig)
    {
        $this->storage = [];
        $this->config = $mainConfig;
    }

    private function clear(): void
    {
        $this->storage = [];
    }

    public function add(Instruction $instruction): void
    {
        $this->storage[] = $instruction;
    }

    // ========================================================================
    private static function makeTargets(array $targets, ReadingOneFile $fileData): array
    {
        $ret = [];

        foreach ($targets as $t) {

            if ($t === '.')
                $t = (string) $fileData->fileInfo;
            elseif (false === \strpos('/', $t) || \str_starts_with('./', $t))
                $t = "{$fileData->fileInfo->getPathInfo()}/$t";

            $ret[] = $t;
        }
        return $ret;
    }

    private static function getTargetIdentifier(array $targets): string
    {
        \sort($targets);
        return \implode('//', $targets);
    }

    // ========================================================================
    public function flushOnFile(ReadingOneFile $fileData): void
    {
        $finfo = $fileData->fileInfo;
        $groupByIds = [];
        $targetInfos = [];

        // Group by target
        foreach ($this->storage as $storageItem) {
            $targets = $this->makeTargets($storageItem->getTargets(), $fileData);
            $tkey = $this->getTargetIdentifier($targets);
            $gid = $ids[$tkey] ??= $this->groupId ++;

            foreach ($targets as $t)
                $targetInfos[$t][$gid] = null;

            $groupByIds[$tkey][] = $storageItem;
        }
        $targetInfos = \array_map(\array_keys(...), $targetInfos);

        $infosToSave = [];

        foreach ($groupByIds as $targets => $storageItems) {

            foreach ($storageItems as $storageItem) {
                $subject = $storageItem->getSubject();

                $infosToSave[$ids[$targets]][] = [
                    'tags' => $storageItem->getTags(),
                    'text' => $storageItem->generate()
                ];
            }
        }
        $fileDir = "{$finfo->getPathInfo()}/";

        if (! is_dir($fileDir))
            mkdir($fileDir, 0777, true);

        \Help\IO::printPHPFile("$fileDir/{$finfo->getFileName()}.php", $infosToSave);
        \Help\IO::printPHPFile("$fileDir/{$finfo->getFileName()}.target.php", $targetInfos);
        $this->clear();
    }
}