<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP;

use Time2Split\PCP\Action\BaseAction;
use Time2Split\PCP\Action\Phase;
use Time2Split\PCP\Action\PhaseName;
use Time2Split\PCP\Action\PhaseState;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\AbstractMoreActions;
use Time2Split\PCP\Action\IMoreActions;
use Time2Split\PCP\Action\MoreActions;

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

    public function onMessage(CContainer $ccontainer): IMoreActions
    {
        if ($ccontainer->isPCPPragma()) {
            $pcpPragma = $ccontainer->getPCPPragma();

            if ($this->waitingForInstructions()) {
                $this->doStoreInstruction($pcpPragma);
                goto end;
            } elseif ($pcpPragma->getCommand() === 'block') {
                $this->doIt($pcpPragma);
                goto end;
            } elseif ($pcpPragma->getCommand() === 'include') {
                return $this->doInclude($pcpPragma);
            }
        }
        if ($this->waitingForInstructions())
            throw new \Exception("Waiting for some 'block' actions, has:\n'{$ccontainer->getCElement()}'");

        end:
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

    private function doStoreInstruction(PCPPragma $pcpPragma): void
    {
        if ($pcpPragma->getCommand() === 'block') {
            $args = $pcpPragma->getArguments();

            if (isset($args['end'])) {
                $this->config["@block.{$this->currentBlock->id}"] = $this->currentBlock;
                $this->currentBlock = null;
            } else {
                throw new \Exception("Nested 'block' are not allowed");
            }
        } else
            $this->currentBlock->addAction($pcpPragma);
    }

    private static function blockStorage(string $id, Configuration $args): IMoreActions
    {
        return new class($id, $args) extends AbstractMoreActions {

            public function __construct(
                public readonly string $id,

                public Configuration $config,
            ) {
                parent::__construct([], $config);
            }

            public function addAction(PCPPragma $action)
            {
                $this->actions[] = $action;
            }
        };
    }

    // ========================================================================

    private function doInclude(PCPPragma $pcpPragma): IMoreActions
    {
        $id = $this->getExprIdentifier($pcpPragma, 'block');
        $blockMoreActions = $this->config["@block.$id"] ?? null;

        if (null === $blockMoreActions)
            return MoreActions::empty();

        $includeConfig = $pcpPragma->getArguments();
        $includeConfig->removeNode('@expr');
        return MoreActions::create(
            $blockMoreActions->getActions(),
            $includeConfig
        );
    }

    // ========================================================================


    private function doIt(PCPPragma $pcpPragma): void
    {
        $id = $this->getExprIdentifier($pcpPragma, 'block');
        $args = $pcpPragma->getArguments();
        $args = clone $args;
        $args->removeNode('@expr');
        $this->currentBlock = self::blockStorage($id, $args);
    }
}
