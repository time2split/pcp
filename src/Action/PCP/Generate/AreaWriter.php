<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Config\Entry\ReadingMode;
use Time2Split\Help\Arrays;
use Time2Split\PCP\Action\Actions;
use Time2Split\PCP\Action\PCP\Generate;
use Time2Split\PCP\App;
use Time2Split\PCP\File\Section;
use Time2Split\PCP\File\StreamInsertion;

final class AreaWriter
{
    private string $srcTimeFormat;

    public function __construct(
        private StreamInsertion $writer,
        private int $srcTime,
        private array $genCodes,
        private string $srcFile,
        private Configuration $config
    ) {
        $this->srcTimeFormat = \date(DATE_ATOM, $srcTime);

        foreach ($genCodes as $code)
            $code->getTags()[Generate::tag_remains] = true;
    }

    private function makeSrcSectionArguments(): Configuration
    {
        return App::configuration([
            'src' => $this->srcFile,
            'mtime' => $this->srcTime - 1
        ]);
    }

    public function write(Area $area): void
    {
        $areaSections = $area->getSections();

        if (empty($areaSections)) {
            // No section of code has been already written
            $writeEnd = true;
            $sectionArgs = $this->makeSrcSectionArguments();
            $srcSection = Section::createPoint($area->getPCPPragma()->getFileSection()->end);
            $lastSection = $srcSection;
        } else {
            $writeEnd = false;
            $lastSection = \array_pop($areaSections);

            // If there is a section (begin) then it must be an 'end' section right after
            assert(! empty($areaSections));

            foreach ($areaSections as $section) {
                $sectionArgs = $area->getSectionArguments($section);

                if ($sectionArgs['src'] === $this->srcFile) {
                    $srcSection = $section;
                    break;
                }
            }

            // Source section not found
            if (! isset($srcSection)) {
                $sectionArgs = $this->makeSrcSectionArguments();
                $srcSection = Section::createPoint($lastSection->begin);
            }
        }
        $this->writeSection($area, $srcSection, $sectionArgs, $writeEnd);
        $this->writer->seekSet($lastSection->end->pos);
    }

    /**
     * Select the code to write for the area
     */
    private function selectCodeToWrite(Area $area): array
    {
        $areaConfig = Configurations::emptyChild(clone $area->getArguments());
        $selectedCodes = [];

        foreach ($this->genCodes as $code) {
            $areaConfig->clear();
            $conditions = $areaConfig->getOptional('@expr', ReadingMode::RawValue);

            if (!$conditions->isPresent())
                $check = true;
            else {
                $conditions = Arrays::ensureArray($conditions->get());
                $codeConfig = Configurations::emptyTreeCopyOf($this->config)->merge(['tags' => $code->getTags()]);
                $check = Actions::checkInterpolations($codeConfig, null, ...$conditions);
            }

            if ($check) {
                // Remove 'remaining' tag
                $code->getTags()[Generate::tag_remains] = false;
                $selectedCodes[] = $code;
            }
        }
        return $selectedCodes;
    }

    private function writeSection(Area $area, Section $section, Configuration $sectionArguments, bool $writeEnd): void
    {
        $sectionMTime = (int) $sectionArguments['mtime'];

        // No need to write
        if ($sectionMTime >= $this->srcTime)
            $writer = null;
        else
            $writer = $this->writer;

        $selectedCodes = $this->selectCodeToWrite($area);

        if (isset($writer)) {
            $writer->seekSet($section->begin->pos);
            $writer->seekSkip($section->end->pos);

            $areaSection = $area->getPCPPragma()->getFileSection();

            // Must add a eol char
            $eof = $areaSection->begin->line === $areaSection->end->line;
            if ($eof)
                $writer->write("\n");

            if (! empty($selectedCodes)) {
                $writer->write("#pragma pcp generate begin mtime=$this->srcTime src=\"$this->srcFile\"\n// $this->srcTimeFormat\n");
                foreach ($selectedCodes as $code)
                    $writer->write("{$code->getText()}\n");

                if ($writeEnd) {
                    $this->writer->write("#pragma pcp generate end");

                    if (!$eof)
                        $this->writer->write("\n");
                }
            }
        }
    }

    public function noInsertion(): bool
    {
        return $this->writer->insertionCount() === 0;
    }

    public function close()
    {
        $this->writer->close();
    }
}
