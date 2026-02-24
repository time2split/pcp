<?php

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Help\TriState;
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
        $fsTarget = FS::pathOf($instructionTarget);

        if ($fsTarget->rooted() === TriState::Yes) {
            $fsTarget = $fsTarget->setRooted(TriState::No);
        } else {
            $fsDir = $fileData->fsFile->getPath();
            $fsTarget = $fsDir->append($fsTarget);
        }
        $fsTarget = $fsTarget->canonical();

        if ($fsTarget->leafed() === TriState::No) {
            $file = $fileData->fsFile->getLast();
            $fsTarget = $fsTarget->appendFile($file);
        }

        if ($fsTarget->count() === 0) {
            return (string)$fileData->fsFile;
        }

        if (!$fsTarget->isCanonical()) {
            throw new \InvalidArgumentException("The result path go outside the base directory: $fsTarget");
        }
        return (string)$fsTarget;
    }
}
