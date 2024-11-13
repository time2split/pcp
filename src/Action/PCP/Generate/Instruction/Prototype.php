<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElementType;

final class Prototype extends Instruction
{

    public function __construct(CElement $subject, Configuration $instruction)
    {
        parent::__construct($subject, $instruction);
        $this->tags['prototype'] = true;
    }

    public function generate(): string
    {
        $subject = $this->getSubject();
        $etypes = $subject->getElementType();

        if ($etypes[CElementType::Function])
            return $this->generatePrototype($subject, $this->getArguments()) . ';';

        throw new \Exception("Cannot generate a prototype from a {$subject->getElementType()} element");
    }

    public function getTargets(): array
    {
        $iconfig = $this->getArguments();
        return (array) ($iconfig['targets.prototype'] ?? $iconfig['targets']);
    }

    // ========================================================================
    private static function generateName(string $baseName, Configuration $arguments): string
    {
        $arguments['name.base'] = $baseName;
        return $arguments['name.format'] ?? $baseName;
    }

    public static function generatePrototype(CDeclaration $subject, Configuration $arguments)
    {
        $subject = clone $subject;
        $identifierPos = $subject['identifier']['pos'];
        $subject['items'][$identifierPos] = self::generateName($subject['items'][$identifierPos], $arguments);

        // Drop some specifiers
        $drop = (array) $arguments['drop'];
        $drop = \array_combine($drop, \array_fill(0, \count($drop), true));

        for ($i = 0, $c = (int) $subject['infos']['specifiers.nb']; $i < $c; $i++) {
            $s = &$subject['items'][$i];

            if (isset($drop[$s]))
                $s = (string)null;
        }
        unset($s);

        // Add some specifiers
        $add = (array)$arguments['add'];
        $addList = [];

        foreach ($add as $v) {

            if (!\array_search($v, $subject['items']))
                $addList[] = $v;
        }
        $items = &$subject['items'];

        $items = \array_merge($addList, $items);
        unset($items);

        return self::prototypeToString($subject);
    }

    private static function prototypeToString(CDeclaration $declaration): string
    {
        $ret = '';
        $lastIsAlpha = false;
        $paramSep = '';

        foreach ($declaration['items'] as $s) {

            if ($s instanceof CDeclaration) {
                $ret .= $paramSep . self::prototypeToString($s);
                $paramSep = ', ';
            } else {
                $len = \strlen($s);

                if ($len == 0)
                    continue;

                if ($lastIsAlpha && ! \ctype_punct($s)) {
                    $ret .= " $s";
                } else {
                    $lastIsAlpha = $len > 0 ? \ctype_alpha($s[$len - 1]) : false;
                    $ret .= $s;
                }
            }
        }
        return $ret;
    }
}
