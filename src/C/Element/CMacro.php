<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Arrays;
use Time2Split\PCP\Action\InstructionArgs;
use Time2Split\PCP\C\CReaderElement;

final class CMacro extends CReaderElement
{
    use CElementTypeTrait;

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

        $args = Arrays::listValueAsKey($args, true);
        $this->fileCursors = $cursors;
        $argsk = \array_keys(\array_slice($args, 0, 2));
        $this->first = $argsk[0] ?? null;
        $this->cmd = $argsk[1] ?? null;

        // Remove the 'first' & 'cmd' arguments
        $this->args = \array_slice($args, 2);
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
        $args = empty($elements) ? InstructionArgs::parse($text) : [];

        return new self($directive, $args, $elements, $text, $cursors);
    }

    public static function fromText(string $text): self
    {
        $args = InstructionArgs::parse($text);
        return new self('pragma', $args, [], $text, []);
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

    public function __toString()
    {
        return "$this->directive $this->text";
    }
}