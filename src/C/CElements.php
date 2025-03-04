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

    public static function createSimpleCPPDirective(string $directive, string $text, Section $fileSection): CPPDirective
    {
        return new class($directive, $text, $fileSection)
        extends BaseCPPDirective
        {
            public function getElementType(): Set
            {
                return CElementType::ofCPP();
            }
        };
    }

    public static function createCPPDefine(string $text, string $id, string $parameters, string $tokens, Section $fileSection): CPPDefine
    {
        if ($parameters === '')
            return new class($text, $fileSection, $id, $tokens)
            extends BaseCPPDirective
            implements CPPDefine
            {
                public function __construct(
                    string $text,
                    Section $fileSection,
                    private string $id,
                    private string $tokens,
                ) {
                    parent::__construct('define', $text, $fileSection);
                }
                public function getElementType(): Set
                {
                    return CElementType::ofCPPDefine();
                }

                public function getTokensText(): string
                {
                    return $this->tokens;
                }

                public function getParameters(): array
                {
                    return [];
                }

                public function getParametersText(): string
                {
                    return '';
                }

                public function getID(): string
                {
                    return $this->id;
                }
            };
        else {
            return new class($text, $fileSection, $id, $tokens, $parameters)
            extends BaseCPPDirective
            implements CPPDefine
            {
                private array $parameters;

                public function __construct(
                    string $text,
                    Section $fileSection,
                    private string $id,
                    private string $tokens,
                    private string $parametersText,
                ) {
                    parent::__construct('define', $text, $fileSection);

                    $buff = \trim(\substr($parametersText, 1, -1));

                    if ($buff === '')
                        $this->parameters = [];
                    else
                        foreach (\explode(',', $buff) as $p)
                            $this->parameters[] = \trim($p);
                }
                public function getElementType(): Set
                {
                    return CElementType::ofCPPDefineFunction();
                }

                public function getTokensText(): string
                {
                    return $this->tokens;
                }

                public function getParameters(): array
                {
                    return $this->parameters;
                }

                public function getParametersText(): string
                {
                    return $this->parametersText;
                }

                public function getID(): string
                {
                    return $this->id;
                }
            };
        }
    }
}
