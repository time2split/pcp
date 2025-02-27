<?php

namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Set;
use Time2Split\PCP\C\CReader;
use Time2Split\PCP\File\Section;

final class CPPDefine extends CPPDirective
{
    private function __construct(
        string $definitionText,
        Section $cursors,
        private string $name,
        private array $arguments,
        private string $text
    ) {
        parent::__construct('define', $definitionText, $cursors);
    }

    public static function createCPPDefine(string $definitionText, Section $cursors): CPPDirective
    {
        $element = CReader::parseCPPDefine($definitionText);

        // Parsing error
        if (null === $element)
            return CPPDirective::create('define', $definitionText, $cursors);

        return new self($definitionText, $cursors, $element['name'], $element['params'], $element['text']);
    }

    public function getElementType(): Set
    {
        return CElementType::of(CElementType::CPP, CElementType::Definition);
    }

    public function isFunction(): bool
    {
        return empty($this->arguments);
    }

    public function getMacroParameters(): array
    {
        return $this->arguments;
    }

    public function getMacroContents()
    {
        return $this->text;
    }
}
