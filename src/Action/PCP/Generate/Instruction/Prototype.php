<?php
namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElementType;

final class Prototype extends Instruction
{

    public function __construct(CElement $subject, Configuration $instruction, \SplFileInfo $sourceFile)
    {
        parent::__construct($subject, $instruction, $sourceFile);
    }

    public function generate(): string
    {
        $subject = $this->getSubject();

        switch ($subject->getElementType()) {
            case CElementType::Prototype:
            case CElementType::Function:
                return $this->generatePrototype();
                break;
            case CElementType::Macro:
                throw new \Exception("Cannot generate a prototype from a Macro element");
        }
    }

    public function getTargets(): array
    {
        $iconfig = $this->getArguments();
        return (array) ($iconfig['targets.prototype'] ?? $iconfig['targets']);
    }

    // ========================================================================
    private function generateName(string $baseName): string
    {
        $conf = $this->getArguments();
        $conf['name.base'] = $baseName;
        return $conf['name.format'] ?? $baseName;
    }

    private function generatePrototype(): string
    {
        return $this->generatePrototype_() . ';';
    }

    private function generatePrototype_(): string
    {
        $subject = clone $this->getSubject();

        $identifierPos = $subject['identifier']['pos'];
        $subject['items'][$identifierPos] = $this->generateName($subject['items'][$identifierPos]);

        // Drop some specifiers
        $args = $this->getArguments();
        $drop = (array) $args['drop'];
        $drop = \array_combine($drop, \array_fill(0, \count($drop), true));

        for ($i = 0, $c = (int) $subject['infos']['specifiers.nb']; $i < $c; $i ++) {
            $s = &$subject['items'][$i];

            if (isset($drop[$s]))
                $s = null;
        }
        unset($s);
        return $this->prototypeToString($subject);
    }

    private function prototypeToString(CDeclaration $declaration): string
    {
        $ret = '';
        $lastIsAlpha = false;
        $paramSep = '';

        foreach ($declaration['items'] as $s) {

            if ($s instanceof CDeclaration) {
                $ret .= $paramSep . $this->prototypeToString($s);
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