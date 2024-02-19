<?php
namespace Time2Split\PCP\Action\PCP\Generate;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\File\CursorPosition;
use Time2Split\Config\Configuration;

final class Areas
{
    use NotInstanciable;

    public static function create(CursorPosition $a, CursorPosition $b, Configuration $arguments): Area
    {
        return new class($a, $b, $arguments) implements Area {

            private readonly array $cursors;

            public function __construct(CursorPosition $a, CursorPosition $b, private Configuration $arguments)
            {
                $this->cursors = [
                    $a,
                    $b
                ];
            }

            public function getFileCursors(): array
            {
                return $this->cursors;
            }

            public function getArguments(): Configuration
            {
                return $this->arguments;
            }
        };
    }
}