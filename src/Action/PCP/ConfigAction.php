<?php
declare(strict_types = 1);
namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\App;
use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;

final class ConfigAction extends BaseAction
{

    private Configuration $instructions;

    private bool $waitingForInstructions;

    private ?string $id;

    public function hasMonopoly(): bool
    {
        return $this->waitingForInstructions;
    }

    public function noExpandAtConfig(): bool
    {
        return $this->waitingForInstructions;
    }

    public function onMessage(CContainer $ccontainer): array
    {
        unset($this->config['@config']);

        if ($ccontainer->isPCPPragma()) {
            $pcpPragma = $ccontainer->getPCPPragma();

            if ($pcpPragma->getCommand() === 'config' || $this->waitingForInstructions) {
                $this->doConfig($pcpPragma);
                return [];
            }
        }

        if ($this->waitingForInstructions)
            throw new \Exception("Waiting for 'config' cpp pragma actions, has '{$ccontainer->getCElement()}'");

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

                if ($phase->state === PhaseState::Start)
                    $this->waitingForInstructions = false;
                elseif ($phase->state === PhaseState::Stop) {

                    if ($this->waitingForInstructions)
                        throw new \Exception("Waiting for 'end' of 'config' block; reached end of file");
                }
                break;
        }
    }

    private function doConfig(PCPPragma $pcpPragma): void
    {
        $args = $pcpPragma->getArguments();

        if ($this->waitingForInstructions) {

            if ($pcpPragma->getCommand() === 'config') {

                if (isset($args['end'])) {
                    $this->config[$this->id] = Configurations::unmodifiable($this->instructions);
                    $this->waitingForInstructions = false;
                } else
                    $this->storeInstruction($pcpPragma);
            } else
                throw new \Exception("Waiting for a config pragma, has $pcpPragma");
        } else {
            $id = $args->getOptional('id');

            if ($id->isPresent()) {

                if ($this->readingDirPhase !== PhaseState::Start)
                    throw new \Exception("'config' action definition can only be set on a directory config file");

                $this->id = "@config.{$id->get()}";
                $this->waitingForInstructions = true;
                $this->instructions = App::emptyConfiguration();
                return;
            }
            $include = $args->getOptional('include');

            if ($include->isPresent()) {
                $id = (string) $include->get();

                // expand the commands
                $iconfig = $this->config["@config.$id"] ?? null;

                if (isset($iconfig))
                    // TODO: make it unmodifiable
                    $this->config['@config'] = $iconfig;

                return;
            }
            $this->config['@config'] = Configurations::unmodifiable($pcpPragma->getArguments());
        }
    }

    private function storeInstruction(PCPPragma $pcpPragma): void
    {
        $this->instructions->merge($pcpPragma->getArguments()
            ->getRawValueIterator());
    }
}