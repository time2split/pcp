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

    private \Data\IConfig $config;

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
            $s->onPhase(\Action\Phase::create($name, $state), $data);
    }

    public function process(\Data\IConfig $config): void
    {
        $this->config = $config->child();
        $this->config['dateTime'] = $date = new \DateTime();
        $this->config['dateTime.format'] = $date->format(\DateTime::ATOM);

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

        foreach ($config['paths'] as $dir)
            $this->processDir($dir);

        $this->updatePhase( //
        \Action\PhaseName::ProcessingFiles, //
        \Action\PhaseState::Stop //
        );
    }

    public function processDir($wdir): void
    {
        $phaseData = \Action\PhaseData\ReadingDirectory::fromPath($wdir);
        $this->updatePhase( //
        \Action\PhaseName::OpeningDirectory, //
        \Action\PhaseState::Start, //
        $phaseData);

        $it = new \FileSystemIterator($wdir);
        $dirs = [];

        foreach ($it as $finfo) {

            if ($it->isDir())
                $dirs[] = $finfo;
            elseif (\in_array(\substr($finfo, - 2), [
                '.h',
                '.c'
            ]))
                $this->processOneFile($finfo);
        }

        foreach ($dirs as $d)
            $this->processDir($d);

        $this->updatePhase( //
        \Action\PhaseName::OpeningDirectory, //
        \Action\PhaseState::Stop, //
        $phaseData);
    }

    private function processOneFile(\SplFileInfo $finfo): void
    {
        $phaseData = \Action\PhaseData\ReadingOneFile::fromPath($finfo);
        $this->updatePhase( //
        \Action\PhaseName::ReadingOneFile, //
        \Action\PhaseState::Start, //
        $phaseData);

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
        \Action\PhaseState::Stop, //
        $phaseData);
    }
}
