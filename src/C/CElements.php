<?php

declare(strict_types=1);

namespace Time2Split\PCP\C;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Set;
use Time2Split\Help\Sets;
use Time2Split\PCP\C\_internal\BaseCPPDirective;
use Time2Split\PCP\C\Element\CElement;
use Time2Split\PCP\C\Element\CElementType;
use Time2Split\PCP\C\Element\CPPDefine;
use Time2Split\PCP\C\Element\CPPDirective;
use Time2Split\PCP\File\Section;

final class CElements
{
    use NotInstanciable;

    public static function tagsOf(CElement $element): Set
    {
        $tags = [];

        // Add the subject types as tags
        foreach ($element->getElementType() as $t)
            $tags[] = 'from.' . \strtolower($t->name);

        \sort($tags);
        $ret = Sets::arrayKeys();
        $ret->setMore(...$tags);
        return $ret;
    }

    private static CElement $null;

    public static function null()
    {
        return self::$null ??= new class() implements CElement
        {
            public function getElementType(): Set
            {
                return CElementType::of();
            }
        };
    }

    // ========================================================================

    final public static function cppDirectiveFromText(string $directive, string $text, Section $fileSection): CPPDirective
    {
        if ($directive === 'define')
            return self::createDefine($text, $fileSection);
        else
            return new BaseCPPDirective($directive, $text, $fileSection);
    }

    private static function createDefine(string $text, Section $fileSection): CPPDefine
    {
        $element = CReader::parseCPPDefine($text);

        if (null === $element)
            throw new \InvalidArgumentException('Is not a define directive');

        return new class(
            $text,
            $fileSection,
            $element['name'],
            $element['params'],
            $element['text']
        )
        extends BaseCPPDirective
        implements CPPDefine
        {
            public function __construct(
                string $definitionText,
                Section $cursors,
                private string $name,
                private array $arguments,
                private string $text
            ) {
                parent::__construct('define', $definitionText, $cursors);
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
        };
    }
}
