<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\PCP\Action\PhaseData\ReadingOneFile;

final class InstructionStorage
{

    private int $groupId;

    private array $storage;

    private ReadingOneFile $sourceFileData;

    public function __construct(ReadingOneFile $sourceFileData)
    {
        $this->groupId = 0;
        $this->targets = new TargetStorage();
        $this->storage = [];
        $this->sourceFileData = $sourceFileData;
    }

    public function put(Instruction $instruction): void
    {
        $this->storage[] = $instruction;

        foreach ($instruction->getTargets() as $t) {
            $t = self::makeTarget($t, $this->sourceFileData);
        }
    }

    public function getTargetsCode(): TargetsCode
    {
        $targetsCode = new TargetsCode();
        $targetStorage = new TargetStorage();

        foreach ($this->storage as $instruction) {
            $targets = [];

            foreach ($instruction->getTargets() as $target) {
                $target = self::makeTarget($target, $this->sourceFileData);
                $targets[] = $targetStorage->getTarget($target);
            }
            $text = $instruction->generate();
            $tags = $instruction->getTags();
            $code = GeneratedCode::create($text, ...$tags);
            $targetsCode->putCode($code, ...$targets);
        }
        return $targetsCode;
    }

    // ========================================================================
    private static function makeTarget(string $targetPath, ReadingOneFile $fileData): string
    {
        if ($targetPath === '.')
            return (string) $fileData->fileInfo;
        elseif (false === \strpos('/', $targetPath) || \str_starts_with('./', $targetPath))
            return "{$fileData->fileInfo->getPath()}/$targetPath";

        return $targetPath;
    }
}