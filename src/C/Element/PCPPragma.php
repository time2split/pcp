<?php
declare(strict_types = 1);
namespace Time2Split\PCP\C\Element;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\Help\FIO;
use Time2Split\Help\IO;
use Time2Split\Help\Traversables;
use Time2Split\PCP\Expression\Expressions;
use Time2Split\PCP\File\Section;

final class PCPPragma extends CPPDirective
{

    private function __construct( //
    string $directive, string $text, Section $cursors, //
    private string $cmd, //
    private string $textArgs, //
    private Configuration $arguments)
    {
        parent::__construct($directive, $text, $cursors);
    }

    public static function createPCPPragma( //
    Configuration $pcpConfig, //
    string $directive, string $text, Section $cursors, //
    $subTextStream = null): //
    PCPPragma
    {
        if (! isset($subTextStream))
            $subTextStream = IO::stringToStream($text);

        FIO::streamSkipChars($subTextStream, \ctype_space(...));
        $cmd = FIO::streamGetCharsUntil($subTextStream, \ctype_space(...));
        $textArgs = \stream_get_contents($subTextStream);

        $args = Configurations::emptyCopyOf($pcpConfig);

        try {
            Expressions::arguments()->tryString($textArgs)
                ->output()
                ->get($args);
        } catch (\Exception $e) {
            throw new \Exception("Unable to parse the pragma arguments '$text' {$cursors->begin}) ; {$e->getMessage()}");
        }
        return new self($directive, $text, $cursors, $cmd, $textArgs, $args);
    }

    public function __clone()
    {
        $this->arguments = clone $this->arguments;
    }

    public function getCommand(): string
    {
        return $this->cmd;
    }

    public function getArguments(): Configuration
    {
        return $this->arguments;
    }

    public function copy(Configuration $arguments = null): self
    {
        return new self($this->getDirective(), $this->getText(), $this->getFileSection(), $this->cmd, $this->textArgs, $arguments ?? clone $this->arguments);
    }

    public function shiftArguments(): self
    {
        list ($cmd,) = Traversables::firstKeyValue($this->arguments);
        $args = Configurations::of($this->arguments);
        unset($args[$cmd]);

        return new self($this->getDirective(), $this->getText(), $this->getFileSection(), $cmd, $this->textArgs, $args);
    }
}
