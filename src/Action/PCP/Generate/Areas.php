<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\C\Element\PCPPragma;
use Time2Split\PCP\File\Section;

final class Areas
{
    use NotInstanciable;

    public static function create(
        PCPPragma $pragma,
        Configuration $arguments,
        \SplObjectStorage $sectionsArguments,
        Section ...$sections
    ): Area {
        return new class($pragma, $arguments, $sectionsArguments, $sections) implements Area {

            private readonly array $cursors;

            public function __construct(
                private PCPPragma $pragma,
                private Configuration $arguments,
                private \SplObjectStorage $sectionsArguments,
                private readonly array $sections
            ) {}

            public function getPCPPragma(): PCPPragma
            {
                return $this->pragma;
            }

            public function getSections(): array
            {
                return $this->sections;
            }

            public function getArguments(): Configuration
            {
                return $this->arguments;
            }

            public function getSectionArguments(Section $section): Configuration
            {
                return $this->sectionsArguments[$section];
            }
        };
    }
}
