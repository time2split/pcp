<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\Action\ActionCommand;
use Time2Split\PCP\File\HasFileSection;
use Time2Split\PCP\File\Section;

final class Areas
{
    use NotInstanciable;

    public static function create(
        ActionCommand $command,
        Configuration $arguments,
        \SplObjectStorage $sectionsArguments,
        Section ...$sections
    ): Area {
        return new class($command, $arguments, $sectionsArguments, $sections) implements Area {

            private readonly array $cursors;

            public function __construct(
                private ActionCommand $command,
                private Configuration $arguments,
                private \SplObjectStorage $sectionsArguments,
                private readonly array $sections
            ) {}

            public function getActionCommand(): ActionCommand&HasFileSection
            {
                return $this->command;
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
