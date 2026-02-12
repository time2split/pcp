<?php

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\App;
use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\File\HasFileSection;
use Time2Split\PCP\FS\FSDir;
use Time2Split\PCP\FS\FSFile;
use Time2Split\PCP\PCP;

final class GenerateClean extends BaseAction
{
    private FSFile $tmpFile;

    private FSDir $fsSrcRoot;

    private FSDir $fsWDRoot;

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::ProcessingFiles:

                if (PhaseState::Start == $phase->state) {
                    $this->fsSrcRoot = $data->fsSrcRoot;
                    $this->fsWDRoot = $data->fsWDRoot;
                    $this->tmpFile = $this->fsWDRoot->appendFile('tmp');
                }
                break;

            case PhaseName::ReadingOneFile:

                if ($phase->state === PhaseState::Start) {
                    $this->processFile($data->fsFile);
                }
                break;
        }
    }

    private function processFile(FSFile $fsFile): void
    {
        $finfo = $fsFile->info();

        if (! \in_array(\substr($finfo, -2), [
            '.h',
            '.c'
        ]))
            return;

        $creader = PCP::creaderOf($fsFile, $this->config);
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
