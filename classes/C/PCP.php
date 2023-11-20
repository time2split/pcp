<?php
namespace C;

/**
 * PHP: C preprocessor
 *
 * @author zuri
 * @date 02/07/2022 12:36:56 CEST
 */
class PCP extends \DataFlow\BasePublisher
{

    private \Data\TreeConfig $config;

    private \Data\TreeConfig $fileConfig;

    public function __construct()
    {
        parent::__construct();
    }

    private function deliverMessage(\Action\IActionMessage $d)
    {
        foreach ($this->getSubscribers() as $s)
            $s->onMessage($d);
    }

    private function updatePhase(\Action\PhaseName $name, \Action\PhaseState $state, $data = null)
    {
        foreach ($this->getSubscribers() as $s)
            $s->onPhase(\Action\Phase::create($name, $state), null);
    }

    public function process(\Data\TreeConfig $config, iterable $files): void
    {
        $this->fileConfig = $config->child();
        $this->config = $this->fileConfig->child();

        $this->fileConfig['dateTime'] = $date = new \DateTime();
        $this->fileConfig['dateTime.format'] = $date->format(\DateTime::ATOM);

        // $this->subscribe(new \Action\PCP\EchoAction($config));
        $this->subscribe(new \Action\PCP\Conf($this->config));
        $this->subscribe(new \Action\PCP\Generate($this->config));

        // Init and check phase
        {
            $wd = $this->config['cpp.wd'];

            if (! is_dir($wd))
                \mkdir($wd, 0777, true);
        }

        $this->updatePhase( //
        \Action\PhaseName::ProcessingFiles, //
        \Action\PhaseState::Start //
        );

        $this->updatePhase( //
        \Action\PhaseName::ProcessingFiles, //
        \Action\PhaseState::Run //
        );

        foreach ($files as $finfo) {
            $this->config->clearLevel();
            $this->processOneFile($finfo);
        }
        $this->updatePhase( //
        \Action\PhaseName::ProcessingFiles, //
        \Action\PhaseState::Stop //
        );
    }

    private function processOneFile(\SplFileInfo $finfo): void
    {
        $this->updatePhase( //
        \Action\PhaseName::ReadingOneFile, //
        \Action\PhaseState::Start, //
        \Action\PhaseData\ReadingOneFile::fromPath($finfo) //
        );

        $this->fileConfig['fileInfo'] = $finfo;
        $creader = \C\Reader::fromFile($finfo);
        $pragmas = [];
        $cppNameRef = (array) $this->config['cpp.name'];
        $skip = false;

        $this->updatePhase( //
        \Action\PhaseName::ReadingOneFile, //
        \Action\PhaseState::Run //
        );

        while (null !== ($element = $creader->next())) {

            if ($element instanceof Macro) {

                if ($element->getDirective() === "pragma") {

                    // Do not process unknownn #pragma
                    if (! \in_array($element->getFirstArgument(), $cppNameRef))
                        continue;

                    $cmd = $element->getCommand();

                    // Avoid begin/end blocks
                    if ($skip) {

                        if ($cmd === 'end')
                            $skip = false;
                    } elseif ($cmd === 'begin') {
                        $skip = true;
                        continue;
                    }
                }
            }
            if (! $skip)
                $this->deliverMessage($element);
        }
        $this->updatePhase( //
        \Action\PhaseName::ReadingOneFile, //
        \Action\PhaseState::Stop //
        );
    }
}
