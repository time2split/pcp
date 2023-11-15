<?php
namespace Action\PhaseData;

final class ReadingOneFile
{

    public \SplFileInfo $fileInfos;

    private function __construct(\SplFileInfo $f)
    {
        $this->fileInfos = $f;
    }

    public static function fromPath(string $path): ReadingOneFile
    {
        return new ReadingOneFile(new \SplFileInfo($path));
    }
}