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
        $config = $config->child();
        $this->config = $config;
        $this->subscribe(new \Action\PCP\EchoAction($config));
        $this->subscribe(new \Action\PCP\Conf($config));

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

        $creader = new \C\Reader($finfo);
        $pragmas = [];
        $cppNameRef = $this->config['cpp.name'];
        $skip = false;

        $this->updatePhase( //
        \Action\PhaseName::ReadingOneFile, //
        \Action\PhaseState::Run //
        );

        while (false !== ($element = $creader->next())) {
            $cursor = $element['cursor'];

            if ($element['group'] === DeclarationGroup::cpp && $element['directive'] === 'pragma') {
                $instruction = \Action\Instruction::fromPragmaString($element['text'], $cppNameRef ?? 'pcp');

                // Do not process unknownn #pragma
                if (null === $instruction)
                    continue;

                $cmd = $instruction->getCommand();
                // Avoid begin/end blocks
                if ($skip) {

                    if ($cmd === 'end')
                        $skip = false;

                    continue;
                } elseif ($cmd === 'begin') {
                    $skip = true;
                    continue;
                }
                $this->deliverMessage($instruction);
            } else {
                $this->deliverMessage(Declaration::from($element));
            }
        }
        $this->updatePhase( //
        \Action\PhaseName::ReadingOneFile, //
        \Action\PhaseState::Stop //
        );
    }
}
