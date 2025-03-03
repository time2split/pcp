<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\Action\MoreActions;
use Time2Split\PCP\C\Element\CElement;

final class BlockAction extends BaseAction
{

    private ?object $currentBlock = null;

    public function hasMonopoly(): bool
    {
        return $this->waitingForInstructions();
    }

    public function noExpandAtConfig(): bool
    {
        return $this->waitingForInstructions();
    }

    public function waitingForInstructions(): bool
    {
        return null !== $this->currentBlock;
    }

    public function onCommand(ActionCommand $command): MoreActions
    {
        if ($this->waitingForInstructions()) {
            $this->doStoreInstruction($command);
        } elseif ($command->getName() === 'block') {
            $this->doIt($command);;
        } elseif ($command->getName() === 'include') {
            return $this->doInclude($command);
        }
        return MoreActions::empty();
    }

    public function onMessage(CElement $element): MoreActions
    {
        if ($this->waitingForInstructions())
            throw new \Exception("Waiting for some 'block' actions, has:\n'{$element}'");

        return MoreActions::empty();
    }

    public function onPhase(Phase $phase, $data = null): void
    {
        switch ($phase->name) {

            case PhaseName::ReadingOneFile:

                if ($phase->state === PhaseState::Stop) {

                    if ($this->waitingForInstructions())
                        throw new \Exception("Waiting for 'end' of a 'block' block; end of file is reached");
                }
                break;
        }
    }

    // ========================================================================

    private function doStoreInstruction(ActionCommand $command): void
    {
        if ($command->getName() === 'block') {
            $args = $command->getArguments();

            if (isset($args['end'])) {
                $this->config["@block.{$this->currentBlock->id}"] = MoreActions::create(
                    $this->currentBlock->actions,
                    $this->currentBlock->config,
                );
                $this->currentBlock = null;
            } else {
                throw new \Exception("Nested 'block' are not allowed");
            }
        } else
            $this->currentBlock->actions[] = $command;
    }

    private static function makeCurrentBlock(string $id, Configuration $includeArguments): object
    {
        return new class($id, $includeArguments) {

            public function __construct(
                public readonly string $id,
                public readonly Configuration $config,
                public array $actions = [],
            ) {}
        };
    }

    // ========================================================================

    private function doInclude(ActionCommand $command): MoreActions
    {
        $id = $this->getExprIdentifier($command, 'block');
        $blockMoreActions = $this->config["@block.$id"] ?? null;

        if (null === $blockMoreActions)
            return MoreActions::empty();

        $includeConfig = $command->getArguments();
        $includeConfig->unsetNode('@expr');
        return MoreActions::create(
            $blockMoreActions->getActions(),
            $includeConfig
        );
    }

    // ========================================================================


    private function doIt(ActionCommand $command): void
    {
        $id = $this->getExprIdentifier($command, 'block');
        $args = $command->getArguments();
        $args = clone $args;
        $args->unsetNode('@expr');
        $this->currentBlock = self::makeCurrentBlock($id, $args);
    }
}
