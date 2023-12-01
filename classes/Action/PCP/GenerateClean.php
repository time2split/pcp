<?php
namespace Action\PCP;

final class GenerateClean extends \Action\BaseAction
{

    private string $tmpFile;

    public function onPhase(\Action\Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case \Action\PhaseName::OpeningDirectory:

                if ($phase->state === \Action\PhaseState::Start)
                    $this->processDirectory($data);
                break;
        }
    }

    private function processDirectory(\Action\PhaseData\ReadingDirectory $directoryData): void
    {
        $wd = $this->config['cpp.wd'];
        $this->tmpFile = "$wd/tmp";
        $dinfo = $directoryData->fileInfo;
        $it = new \FileSystemIterator($dinfo);

        foreach ($it as $finfo)
            $this->processFile($finfo);
    }

    private function processFile(\SplFileInfo $finfo): void
    {
        if (! \in_array(\substr($finfo, - 2), [
            '.h',
            '.c'
        ]))
            return;

        $creader = \C\Reader::fromFile($finfo);
        $cppNameRef = (array) $this->config['cpp.name'];
        $waitForEnd = false;
        $fpos = [];

        while (null !== ($element = $creader->next())) {

            if ( //
            ! ($element instanceof \C\Macro) || //
            ! ($element->getDirective() === "pragma") || //
            ! \in_array($element->getFirstArgument(), $cppNameRef))
                continue;

            $cmd = $element->getCommand();
            $cursors = $element->getFileCursors();

            if (! $waitForEnd) {

                if ($cmd === 'begin') {
                    $waitForEnd = true;
                    $fpos[] = $cursors[0]->getPos();
                } elseif ($cmd === 'end')
                    throw new \Exception("Malformed file ($finfo), unexpected 'pragma end' at line {$cursors[0]->getLine()}");
            } else {

                if ($cmd === 'end') {
                    $waitForEnd = false;
                    $fpos[] = $cursors[1]->getPos();
                } elseif ($cmd === 'begin')
                    throw new \Exception("Malformed file ($finfo), unexpected 'pragma begin' at line {$cursors[0]->getLine()}");
            }
        }
        $creader->close();

        if (empty($fpos))
            return;

        $insert = \File\Insertion::fromFilePath($finfo, $this->tmpFile);
        $fpos = \array_reverse($fpos);

        while (! empty($fpos)) {
            $insert->seek(\array_pop($fpos));
            $insert->seekForget(\array_pop($fpos));
        }
        $insert->close();
    }
}