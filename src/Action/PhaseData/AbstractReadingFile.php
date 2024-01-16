<?php
namespace Time2Split\PCP\Action\PhaseData;

abstract class AbstractReadingFile
{

    public readonly \SplFileInfo $fileInfo;

    private function __construct(\SplFileInfo $f)
    {
        $this->fileInfo = $f;
    }

    public final static function fromPath(string $path): static
    {
        return new static(new \SplFileInfo($path));
    }
}