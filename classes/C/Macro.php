<?php
namespace C;

class Macro extends ReaderElement
{

    private ?string $first;

    private ?string $cmd;

    private string $directive;

    private array $args;

    private string $text;

    private array $fileCursors;

    private function __construct(string $directive, array $args, array $elements, string $text, array $cursors)
    {
        parent::__construct($elements);
        $this->directive = $directive;
        $this->text = $text;
        $this->args = \Help\Arrays::listValueAsKey($args, true);
        $this->fileCursors = $cursors;
        $args = \array_keys(\array_slice($this->args, 0, 2));
        $this->first = $args[0] ?? null;
        $this->cmd = $args[1] ?? null;
    }

    public static function fromReaderElements(array $element): self
    {
        $cursors = [
            $element['cursor'],
            $element['cursor2']
        ];
        $directive = $element['directive'];
        $text = $element['text'];
        $elements = $element['elements'] ?? [];
        $args = empty($elements) ? \Action\InstructionArgs::parse($text) : [];

        return new self($directive, $args, $elements, $text, $cursors);
    }

    public static function fromText(string $text): self
    {
        $args = \Action\InstructionArgs::parse($text);
        return new self($args, $cursors);
    }

    public function isFunction(): bool
    {
        return empty($this->args);
    }

    public function getCommand(): ?string
    {
        return $this->cmd;
    }

    public function getFirstArgument(): ?string
    {
        return $this->first;
    }

    public function setArguments(array $args): self
    {
        return new self($this->directive, [
            $this->directive,
            ...$args
        ], $this->elements, $this->text, $this->fileCursors);
    }

    public function getFileCursors(): array
    {
        return $this->fileCursors;
    }

    public function getText(): array
    {
        return $this->text;
    }

    public function getDirective(): string
    {
        return $this->directive;
    }

    public function getArguments(): array
    {
        return $this->args;
    }

    public function sendTo(\Action\IAction $action): bool
    {
        return $action->deliver($this);
    }

    public function __toString()
    {
        return "$this->directive $this->text";
    }
}