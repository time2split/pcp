<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\PCP\C\CReaderElement;
use Time2Split\PCP\Expression\Expressions;

final class CMacro extends CReaderElement
{
    use CElementTypeTrait;

    private ?string $first;

    private ?string $cmd;

    private string $directive;

    private Configuration $args;

    private string $text;

    private array $fileCursors;

    private function __construct(string $directive, Configuration $args, array $elements, string $text, array $cursors)
    {
        parent::__construct($elements);
        $this->directive = $directive;
        $this->text = $text;

        // $args = Arrays::listValueAsKey($args, true);thu
        $this->fileCursors = $cursors;

        $keys = $args->traversableKeys();

        $i = 0;

        foreach ($keys as $k) {
            $argsk[] = $k;
            if (++ $i == 2)
                break;
        }
        $this->first = $argsk[0] ?? null;
        $this->cmd = $argsk[1] ?? null;

        // Remove the 'first' & 'cmd' arguments
        unset($args[$this->first]);
        unset($args[$this->cmd]);
        $this->args = $args;
    }

    private static function parseArguments(string $text): Configuration
    {
        $config = Configurations::empty();
        $parser = Expressions::arguments();
        $result = $parser->tryString($text)->output();
        $result->get($config); // Do the assignation
        return $config;
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
        $args = self::parseArguments($text);

        return new self($directive, $args, $elements, $text, $cursors);
    }

    public static function fromText(string $text): self
    {
        $args = self::parseArguments($text);
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

    public function getArguments(): Configuration
    {
        return $this->args;
    }

    public function __toString()
    {
        return "$this->directive $this->text";
    }
}