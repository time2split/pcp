<?php

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\PCP\Action\PhaseData\ReadingOneFile;
use Time2Split\PCP\FS\FS;

final class InstructionStorage
{

    private array $storage;

    private ReadingOneFile $sourceFileData;

    public function __construct(ReadingOneFile $sourceFileData)
    {
        $this->storage = [];
        $this->sourceFileData = $sourceFileData;
    }

    public function put(Instruction $instruction): void
    {
        $this->storage[] = $instruction;
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
            $tags = $instruction->getTags()->toListOfElements();
            $code = GeneratedCode::create($text, ...$tags);
            $targetsCode->putCode($code, ...$targets);
        }
        return $targetsCode;
    }

    // ========================================================================

    private static function makeTarget(string $instructionTarget, ReadingOneFile $fileData): string
    {
        if ($instructionTarget === '.')
            return FS::fileOf($fileData->fsFile);

        $fsTarget = FS::fileOf($instructionTarget);
        $canonical = $fsTarget->canonical();

        if (!$canonical->isCanonical())
            throw new \Exception("The result path go outside the base directory: $canonical");

        return (string)$canonical;
    }
}
