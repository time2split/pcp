<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\Action\PCP\For\Cond;
use Time2Split\Config\Entry\ReadingMode;
use Time2Split\Help\Arrays;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\CActionSubject;
use Time2Split\PCP\Action\MoreActions;
use Time2Split\PCP\C\Element\CElement;

final class ForAction extends BaseAction
{
    /**
     * Sequence of for instructions per directory loaded from configuration files (pcp.conf)
     */
    private array $forInstructions_dirHierarchy = [[0, []]];

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

    public function onCommand(ActionCommand $command): MoreActions
    {
        if ($command->getName() === 'for' || $this->waitingFor)
            $this->doFor($command);

        return MoreActions::empty();
    }

    public function onMessage(CElement $element): MoreActions
    {
        if ($this->waitingFor)
            throw new \Exception("Waiting for 'for' cpp pragma actions, has '{$element}'");

        return $this->checkForConditions(CActionSubject::of($element));
    }

    private function checkForConditions(CActionSubject $subject): MoreActions
    {
        foreach ($this->forInstructions as $condStorage) {
            $upperConfig = $subject->getConfiguration();
            $check = $condStorage->check($upperConfig, $this->config->getInterpolator());

            if ($check)
                return MoreActions::create($condStorage->instructions);
        }
        return MoreActions::empty();
    }

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::OpeningDirectory:
                /*
                 * Each directory store its own set of instructions
                 */
                if ($phase->state === PhaseState::Start) {
                    [$this->idGen, $this->forInstructions] = Arrays::lastValue($this->forInstructions_dirHierarchy);
                } elseif ($phase->state === PhaseState::Run) {
                    $this->forInstructions_dirHierarchy[] = [$this->idGen, $this->forInstructions];
                } elseif ($phase->state === PhaseState::Stop) {
                    // Clear the directory instructions
                    \array_pop($this->forInstructions_dirHierarchy);
                }
                break;

            case PhaseName::ReadingOneFile:

                if ($phase->state === PhaseState::Start) {
                    [$this->idGen, $this->forInstructions] = Arrays::lastValue($this->forInstructions_dirHierarchy);
                    $this->waitingFor = false;
                } elseif ($phase->state === PhaseState::Stop) {

                    if ($this->waitingFor)
                        throw new \Exception("Waiting for 'end' of 'for' block; reached end of file");
                }
                break;
        }
    }

    private function doFor(ActionCommand $command): void
    {
        $args = $command->getArguments();

        if ($this->waitingFor) {

            // End of 'for' block
            if ($command->getName() === 'for') {

                if (! isset($args['end']))
                    throw new \Exception("Waiting for 'end' of 'for' block");

                $this->waitingFor = false;

                if (empty($this->forInstructions[$this->id]->instructions))
                    unset($this->forInstructions[$this->id]);
            } else {
                $this->storeInstruction($command);
            }
        } elseif (isset($args['clear'])) {
            $this->forInstructions = [];
            $this->idGen = 0;
        } else {
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

            $this->forInstructions[$id] = new Cond($command->getArguments(), ...$cond);
        }
    }

    private function storeInstruction(ActionCommand $command): void
    {
        $this->forInstructions[$this->id]->instructions[] = $command;
    }
}
