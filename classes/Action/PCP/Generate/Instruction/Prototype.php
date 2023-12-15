<?php
namespace Action\PCP\Generate\Instruction;

use Data\IConfig;
use C\Element;
use C\Element\Declaration;
use C\Element\ElementType;

final class Prototype extends \Action\PCP\Generate\Instruction
{

    private function __construct(Element $subject, IConfig $instruction)
    {
        parent::__construct($subject, $instruction);
    }

    public static function create(Element $subject, IConfig $instruction): self
    {
        return new self($subject, $instruction);
    }

    public function generate(): string
    {
        $subject = $this->getSubject();
        $iconfig = $this->getInstruction();

        switch ($subject->getElementType()) {
            case ElementType::Prototype:
            case ElementType::Function:
                return $this->generatePrototype();
                break;
            case ElementType::Macro:
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
        $i = $this->getInstruction();
        $subject = $this->getSubject();

        $generateType = $i['function'] ?? $i['prototype'];

        $identifierPos = $subject['identifier']['pos'];
        $subject['items'][$identifierPos] = $this->generateName($subject['items'][$identifierPos]);

        return $this->prototypeToString($subject);
    }

    private function prototypeToString(Declaration $declaration): string
    {
        $ret = '';
        $lastIsAlpha = false;
        $paramSep = '';

        foreach ($declaration['items'] as $s) {

            if ($s instanceof Declaration) {
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