<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\PCP\Action\PCP\For\Cond;
use Time2Split\Config\Entry\ReadingMode;
use Time2Split\Help\Arrays;
use Time2Split\PCP\Action\CActionSubject;
use Time2Split\PCP\C\CElement;

final class ForAction extends BaseAction
{

    private array $forInstructions;

    private bool $waitingFor;

    private ?string $id;

    private int $idGen;

    public function hasMonopoly(): bool
    {
        return $this->waitingFor;
    }

    public function noExpandAtConfig(): bool
    {
        return $this->waitingFor;
    }

    public function onMessage(CContainer $ccontainer): array
    {
        if ($ccontainer->isPCPPragma()) {
            $pcpPragma = $ccontainer->getPCPPragma();

            if ($pcpPragma->getCommand() === 'for' || $this->waitingFor) {
                $this->doFor($pcpPragma);
                return [];
            }
        }

        if ($this->waitingFor)
            throw new \Exception("Waiting for 'for' cpp pragma actions, has '{$ccontainer->getCElement()}'");

        return $this->checkForConditions(new class($ccontainer->getCElement()) extends CActionSubject {
            public function __construct(CElement $subject)
            {
                parent::__construct($subject);
            }
        });
    }

    private function checkForConditions(CActionSubject $subject): array
    {
        foreach ($this->forInstructions as $condStorage) {
            $upperConfig = $subject->getConfiguration();
            $check = $condStorage->check($upperConfig, $this->config->getInterpolator());

            if ($check)
                return $condStorage->instructions;
        }
        return [];
    }

    private PhaseState $readingDirPhase;

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::OpeningDirectory:
                $this->readingDirPhase = $phase->state;
                break;

            case PhaseName::ReadingOneFile:

                if ($phase->state === PhaseState::Start) {
                    $this->forInstructions = $this->config['action.for'] ?? [];
                    $this->waitingFor = false;
                    $this->idGen = 0;
                } elseif ($phase->state === PhaseState::Stop) {

                    if ($this->waitingFor)
                        throw new \Exception("Waiting for 'end' of 'for' block; reached end of file");

                    /**
                     * Reading a configuration file
                     */
                    if ($this->readingDirPhase === PhaseState::Start)
                        $this->config['action.for'] = $this->forInstructions;
                }
                break;
        }
    }

    private function doFor(PCPPragma $pcpPragma): void
    {
        $args = $pcpPragma->getArguments();

        if ($this->waitingFor) {

            // End of 'for' block
            if ($pcpPragma->getCommand() === 'for') {

                if (! isset($args['end']))
                    throw new \Exception("Waiting for 'end' of 'for' block");

                $this->waitingFor = false;

                if (empty($this->forInstructions[$this->id]->instructions))
                    unset($this->forInstructions[$this->id]);
            } else {
                $this->storeInstruction($pcpPragma);
            }
        } elseif (isset($args['clear']))
            $this->config['for.instructions'] =
                $this->forInstructions = [];
        else {
            // == Create the new 'for' block ==

            $cond = $args->getOptional('@expr', ReadingMode::RawValue);

            if (! $cond->isPresent())
                throw new \Exception('A \'for\' action must have a condition');

            $cond = Arrays::ensureArray($cond->get());

            $this->waitingFor = true;

            $id = $args['id'] ?? null;

            if (! isset($id))
                $id = $this->idGen++;

            $this->id = (string) $id;

            $this->forInstructions[$id] = new Cond($pcpPragma->getArguments(), ...$cond);
        }
    }

    private function storeInstruction(PCPPragma $pcpPragma): void
    {
        $this->forInstructions[$this->id]->instructions[] = $pcpPragma;
    }
}
