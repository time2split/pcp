<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Config\Configuration;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\File\Section;

final class Areas
{
    use NotInstanciable;

    public static function create(Section $fileSection, Configuration $arguments): Area
    {
        return new class($fileSection, $arguments) implements Area {

            private readonly array $cursors;

            public function __construct(private readonly Section $fileSection, private Configuration $arguments)
            {}

            public function getFileSection(): Section
            {
                return $this->fileSection;
            }

            public function getArguments(): Configuration
            {
                return $this->arguments;
            }
        };
    }
}