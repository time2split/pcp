<?php
namespace Time2Split\PCP\Action\PCP\Generate\Instruction;

use Time2Split\Config\Configuration;
use Time2Split\PCP\Action\PCP\Generate\Instruction;
use Time2Split\PCP\C\CElement;
use Time2Split\PCP\C\Element\CDeclaration;
use Time2Split\PCP\C\Element\CElementType;

final class Prototype extends Instruction
{

    private function __construct(CElement $subject, Configuration $instruction)
    {
        parent::__construct($subject, $instruction);
    }

    public static function create(CElement $subject, Configuration $instruction): self
    {
        return new self($subject, $instruction);
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
        $iconfig = $this->getInstruction();
        return (array) ($iconfig['targets.prototype'] ?? $iconfig['targets']);
    }

    // ========================================================================
    private function generateName(string $baseName): string
    {
        $conf = $this->getInstruction();
        $conf['name.base'] = $baseName;
        return $conf['name.format'] ?? $baseName;
    }

    private function generatePrototype(): string
    {
        return $this->generatePrototype_() . ';';
    }

    private function generatePrototype_(): string
    {
        $subject = $this->getSubject();

        $identifierPos = $subject['identifier']['pos'];
        $subject['items'][$identifierPos] = $this->generateName($subject['items'][$identifierPos]);

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