<?php
namespace Time2Split\PCP\C;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\C\Element\CContainer;

final class CElements
{
    use NotInstanciable;

    public static function isPCPCommand(CElement $element, string $cmd): bool
    {
        return //
        CContainer::of($element)->isPCPPragma() && //
        $element->getCommand() === $cmd;
    }
}