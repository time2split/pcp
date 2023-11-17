<?php
namespace Action;

final class Instruction implements \Action\IActionMessage
{

    private array $args;

    private string $cmd;

    private function __construct(array $args)
    {
        $k = \array_key_first($args);

        if ($k === 0)
            $this->cmd = \array_shift($args);
        else
            $this->cmd = '';

        $this->args = $args;
    }

    public static function fromPragmaString(string $text, string $cppName): ?self
    {
        $args = \Action\InstructionArgs::parse($text);
        $cpp = \array_shift($args);

        if (0 === \strcasecmp($cpp, $cppName))
            return new self($args);

        return null;
    }

    public function getCommand(): string
    {
        return $this->cmd;
    }

    public function getArguments(): array
    {
        return $this->args;
    }

    public function sendTo(\Action\IAction $action): bool
    {
        return $action->deliver($this);
    }
}