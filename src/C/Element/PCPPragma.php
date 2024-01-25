<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\Config\Configuration;
use Time2Split\Help\FIO;
use Time2Split\PCP\App;
use Time2Split\PCP\Expression\Expressions;

final class PCPPragma extends CPPDirective
{

    private function __construct( //
    string $directive, string $text, array $cursors, //
    private string $cmd, //
    private string $textArgs, //
    private Configuration $arguments)
    {
        parent::__construct($directive, $text, $cursors);
    }

    public static function createPCPPragma( //
    string $directive, string $text, array $cursors, //
    $subTextStream): //
    PCPPragma
    {
        $cmd = FIO::streamGetCharsUntil($subTextStream, \ctype_space(...));
        $textArgs = \stream_get_contents($subTextStream);

        $args = App::emptyConfiguration();

        try {
            Expressions::arguments()->tryString($textArgs)
                ->output()
                ->get($args);
        } catch (\Exception $e) {
            throw new \Exception("Unable to parse the pragma '$text' {$cursors[0]}) ; {$e->getMessage()}");
        }
        return new self($directive, $text, $cursors, $cmd, $textArgs, $args);
    }

    public function getCommand(): string
    {
        return $this->cmd;
    }

    public function getArguments(): Configuration
    {
        return $this->arguments;
    }
}
