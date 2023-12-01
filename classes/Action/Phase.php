<?php
namespace Action;

final class Phase
{

    public readonly PhaseName $name;

    public readonly PhaseState $state;

    private function __construct(PhaseName $name, PhaseState $state)
    {
        $this->name = $name;
        $this->state = $state;
    }

    public static function create(PhaseName $name, PhaseState $state): Phase
    {
        return new Phase($name, $state);
    }

    public function __toString(): string
    {
        return "{$this->name->name}:{$this->state->name}";
    }
}