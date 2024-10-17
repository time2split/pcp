<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Arrays;
use Time2Split\Help\Iterables;
use Time2Split\PCP\C\CMatching;
use Time2Split\PCP\C\CReaderElement;

final class CDeclaration extends CReaderElement
{
    private array $uinfos;

    private function __construct(array $elements)
    {
        parent::__construct($elements);
        $this->uinfos = [];
    }

    public static function fromReaderElements(array $element)
    {
        return new CDeclaration($element);
    }

    /**
     * Get the specifiers of a function.
     *
     * @return array
     * @throws \DomainException if the declaration is not a function.
     */
    public function getSpecifiers(): array
    {
        if (!$this->getElementType()[CElementType::Function])
            throw new \DomainException('Cannot get the specifiers of ' . CElementType::stringOf($this->getElementType()));

        $ret = [];
        $nb = (int) $this['infos']['specifiers.nb'];

        for ($i = 0; $i < $nb; $i++)
            $ret[] = $this['items'][$i];

        return $ret;
    }

    /**
     * Get the identifier of a function.
     *
     * @return array
     * @throws \DomainException if the declaration has no identifier
     */
    public function getIdentifier(): string
    {
        if (!isset($this['identifier']))
            throw new \DomainException();

        return (string)$this['items'][$this['identifier']['pos']];
    }

    // ========================================================================

    // /* 
    public function getUnknownInfos(): array
    {
        if (!empty($this->uinfos))
            return $this->uinfos;

        return $this->uinfos = self::makeUnknownInfos($this->getArrayCopy());
    }
    // */

    // ========================================================================
    public static function makeUnknownInfos(array $element): array
    {
        $nbSpecifiers = $element['infos']['specifiers.nb'];

        $specifiers = \array_slice($element['items'], 0, $nbSpecifiers);
        $unknown = \array_filter($specifiers, fn($n) => !CMatching::isSpecifier($n));
        $typeSpecifiers = \array_filter($specifiers, fn($n) => CMatching::isTypeSpecifier($n));

        $pointers = \array_slice($element['items'], $nbSpecifiers);
        $pointers = \array_filter($pointers); // Avoid null value (generated identifier)
        list(, $punknown) = Arrays::arrayPartition(
            $pointers,
            fn($n) => $n === '*' || CMatching::isTypeQualifier($n)
        );

        return [
            'specifiers' => [
                'nb' => $nbSpecifiers,
                'unknown.nb' => $n1 = \count($unknown),
                'type.nb' => \count($typeSpecifiers),
                'unknown' => $unknown,
                'type' => $typeSpecifiers
            ],
            'pointers' => [
                'nb' => \count($pointers),
                'unknown.nb' => $n2 = \count($punknown),
                '' => $pointers,
                'unknown' => $punknown
            ],
            'unknown' => [
                'nb' => $n1 + $n2,
                '' => \array_merge($unknown, $punknown)
            ]
        ];
    }

    public function __toString()
    {
        $type = Iterables::mapValue($this->getElementType(), fn($t) => $t->name);
        return "CDeclaration:$type";
    }
}
