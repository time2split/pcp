<?php
declare(strict_types = 1);
namespace Time2Split\PCP\C;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\App;
use Time2Split\PCP\C\Element\CContainer;
use Time2Split\PCP\C\Element\PCPPragma;

final class CElements
{
    use NotInstanciable;

    public static function isPCPCommand(CElement|CContainer $element, string $cmd, string $firstArg = null): bool
    {
        if (! ($element instanceof CContainer))
            $element = CContainer::of($element);

        return //
        $element->isPCPPragma() && //
        self::PCPIsCommand($element->getPCPPragma(), $cmd, $firstArg);
    }

    public static function PCPIsCommand(PCPPragma $pcpPragma, string $cmd, string $firstArg = null): bool
    {
        return //
        $pcpPragma->getCommand() === $cmd && //
        (! isset($firstArg) || $firstArg === App::configFirstKey($pcpPragma->getArguments()));
    }
}