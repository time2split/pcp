<?php
namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\App;
use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PhaseData\ReadingDirectory;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\CPPDirectives;

final class GenerateClean extends BaseAction
{

    private string $tmpFile;

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::OpeningDirectory:

                if ($phase->state === PhaseState::Start)
                    $this->processDirectory($data);
                break;
        }
    }

    private function processDirectory(ReadingDirectory $directoryData): void
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

        $creader = CReader::fromFile($finfo);
        $creader->setCPPDirectiveFactory(CPPDirectives::factory($this->config));
        $waitForEnd = false;
        $fpos = [];

        while (null !== ($element = $creader->next())) {
            $ccontainer = CContainer::of($element);

            if (! $ccontainer->isPCPPragma())
                continue;

            $cmd = $element->getCommand();
            $section = $element->getFileSection();

            if (! $waitForEnd) {

                if ($cmd === 'begin') {
                    $waitForEnd = true;
                    $fpos[] = $section->begin->pos;
                } elseif ($cmd === 'end')
                    throw new \Exception("Malformed file ($finfo), unexpected 'pragma end' at {{$section}}");
            } else {

                if ($cmd === 'end') {
                    $waitForEnd = false;
                    $fpos[] = $section->end->pos;
                } elseif ($cmd === 'begin')
                    throw new \Exception("Malformed file ($finfo), unexpected 'pragma begin' at {{$section}}");
            }
        }
        $creader->close();

        if (empty($fpos))
            return;

        $insert = App::fileInsertion($finfo, $this->tmpFile);
        $fpos = \array_reverse($fpos);

        while (! empty($fpos)) {
            $insert->seekSet(\array_pop($fpos));
            $insert->seekSkip(\array_pop($fpos));
        }
        $insert->close();
    }
}