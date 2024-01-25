<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\Expression\Expressions;

final class CPPDefine extends CPPDirective
{

    private function __construct( //
    string $definitionText, //
    array $cursors, //
    private string $name, //
    private array $parameters, //
    private string $text) //
    {
        parent::__construct('define', $definitionText, $cursors);
    }

    public static function createCPPDefine(string $definitionText, array $cursors): CPPDirective
    {
        $element = CReader::parseCPPDefine($definitionText);

        // Parsing error
        if (null === $element)
            return CPPDirective::create('define', $definitionText, $cursors);

        return new self($definitionText, $cursors, $element['name'], $element['params'], $element['text']);
    }

    private static function parseArguments(string $text): Configuration
    {
        $config = Configurations::empty();
        $parser = Expressions::arguments();
        $result = $parser->tryString($text)->output();
        $result->get($config); // Do the assignation
        return $config;
    }

    public function isFunction(): bool
    {
        return empty($this->parameters);
    }

    public function getMacroParameters(): Configuration
    {
        return $this->parameters;
    }

    public function getMacroContents()
    {
        return $this->text;
    }
}