<?php

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\App;
use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PhaseData\ReadingDirectory;
use Time2Split\PCP\File\HasFileSection;
use Time2Split\PCP\PCP;

final class GenerateClean extends BaseAction
{
    private string $tmpFile;

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::OpeningDirectory:

                if ($phase->state === PhaseState::Start) {
                    $this->processDirectory($data);
                }
                break;
        }
    }

    private function processDirectory(ReadingDirectory $directoryData): void
    {
        $wd = $this->config['pcp.dir'];
        $this->tmpFile = "$wd/tmp";
        $dinfo = $directoryData->fileInfo;
        $it = new \FileSystemIterator($dinfo);

        foreach ($it as $finfo)
            $this->processFile($finfo);
    }

    private function processFile(\SplFileInfo $finfo): void
    {
        if (! \in_array(\substr($finfo, -2), [
            '.h',
            '.c'
        ]))
            return;

        $creader = PCP::creaderOf($finfo, $this->config);
        $waitForEnd = false;
        $fpos = [];

        while (null !== ($element = $creader->next())) {

            if (! ($element instanceof ActionCommand))
                continue;

            /**
             * @var ActionCommand&HasFileSection $element
             */
            $section = $element->getFileSection();

            if (! $waitForEnd) {

                if (Generate::checkActionCommand($element, 'begin')) {
                    $waitForEnd = true;
                    $fpos[] = $section->begin->pos;
                } elseif (Generate::checkActionCommand($element, 'end')) {
                    throw new \Exception("Malformed file ($finfo), unexpected '$element' at {{$section}}");
                }
            } elseif (Generate::checkActionCommand($element, 'end')) {
                $waitForEnd = false;
                $fpos[] = $section->end->pos;
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
        \unlink($this->tmpFile);
    }
}
