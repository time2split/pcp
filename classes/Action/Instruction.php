<?php
namespace Action;

final class Instruction implements \Action\IActionMessage
{

    private array $args;

    private string $cmd;

    private array $fileCursors;

    private function __construct(array $args, array $cursors)
    {
        $k = \array_key_first($args);

        if ($k === 0)
            $this->cmd = \array_shift($args);
        else
            $this->cmd = '';

        $this->args = $args;
        $this->fileCursors = $cursors;
    }

    public static function fromReaderElement(array $element, array $cppNames): ?self
    {
        $cursors = [
            $element['cursor'],
            $element['cursor2']
        ];
        $text = $element['text'];
        $args = \Action\InstructionArgs::parse($text);
        $cpp = \array_shift($args);

        if (\in_array(\strtolower($cpp), $cppNames))
            return new self($args, $cursors);

        return null;
    }

    public function getFileCursors(): array
    {
        return $this->fileCursors;
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