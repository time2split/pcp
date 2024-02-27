<?php
declare(strict_types = 1);
namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\PCP\Action\PCP\For\Cond;
use Time2Split\Config\Interpolation;
use Time2Split\Config\Configurations;

final class ForAction extends BaseAction
{

    private const DefaultConfig = [
        'for' => [
            'id' => null,
            'cond' => false
        ]
    ];

    private array $forInstructions;

    private bool $waitingFor;

    private ?string $id;

    private int $idGen;

    public function hasMonopoly(): bool
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
            throw new \Exception(__CLASS__ . ": waiting for 'for' actions, has '{$ccontainer->getCElement()}'");

        return $this->checkForConditions($ccontainer);
    }

    private function checkForConditions(CContainer $ccontainer): array
    {
        foreach ($this->forInstructions as $condStorage) {
            $config = $condStorage->config;
            $upperConfig = Configurations::hierarchy($this->config, $config);
            $cond = $condStorage->condition;

            if ($cond instanceof Interpolation) {
                $intp = $config->getInterpolator();
                $check = $intp->execute($cond->compilation, $upperConfig);
            } else
                $check = $cond;

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

                if ($phase->state === PhaseState::Start) {
                    $this->forInstructions = [];
                }
                break;

            case PhaseName::ReadingOneFile:

                if ($phase->state === PhaseState::Start) {
                    $this->forInstructions = $this->config['for.instructions'] ?? [];
                    $this->waitingFor = false;
                    $this->idGen = 0;
                } elseif ($phase->state === PhaseState::Stop) {

                    if ($this->readingDirPhase === PhaseState::Start) {
                        $this->config['for.instructions'] = $this->forInstructions;
                    }
                }
                break;
        }
    }

    private function doFor(PCPPragma $pcpPragma): void
    {
        $args = $pcpPragma->getArguments();

        if ($this->waitingFor) {

            // End of 'for' block
            if (isset($args['end'])) {
                $this->waitingFor = false;

                if (empty($this->forInstructions[$this->id]->instructions))
                    unset($this->forInstructions[$this->id]);
            } else
                $this->storeInstruction($pcpPragma);
        } else {
            // == Create the new 'for' block ==

            $cond = $args->getOptional('cond', false);

            if (! $cond->isPresent())
                throw new \Exception('A \'for\' action must have a \'cond\' value set');

            $cond = $cond->get();

            if (! ($cond instanceof Interpolation))
                throw new \Exception("A 'for' condition must be a valid dynamic expression");

            $this->waitingFor = true;
            $id = $args['id'] ?? null;

            if (! isset($id))
                $id = $this->idGen ++;

            $this->id = (string) $id;
            $this->forInstructions[$id] = new Cond($pcpPragma->getArguments(), $cond);
        }
    }

    private function storeInstruction(PCPPragma $pcpPragma): void
    {
        $this->forInstructions[$this->id]->instructions[] = $pcpPragma;
    }
}