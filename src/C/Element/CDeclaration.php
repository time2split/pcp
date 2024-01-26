<?php
namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Arrays;
use Time2Split\PCP\C\CDeclarationGroup;
use Time2Split\PCP\C\CDeclarationType;
use Time2Split\PCP\C\CMatching;
use Time2Split\PCP\C\CReaderElement;

final class CDeclaration extends CReaderElement
{
    use CElementTypeTrait;

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

    public function getGroup(): CDeclarationGroup
    {
        return $this['group'];
    }

    public function getType(): CDeclarationType
    {
        return $this['type'];
    }

    // ========================================================================
    public function getUnknownInfos(): array
    {
        if (! empty($this->uinfos))
            return $this->uinfos;

        return $this->uinfos = self::makeUnknownInfos($this->elements);
    }

    // ========================================================================
    public static function makeUnknownInfos(array $element): array
    {
        $nbSpecifiers = $element['infos']['specifiers.nb'];

        $specifiers = \array_slice($element['items'], 0, $nbSpecifiers);
        $unknown = \array_filter($specifiers, fn ($n) => ! CMatching::isSpecifier($n));
        $typeSpecifiers = \array_filter($specifiers, fn ($n) => CMatching::isTypeSpecifier($n));

        $pointers = \array_slice($element['items'], $nbSpecifiers);
        $pointers = \array_filter($pointers); // Avoid null value (generated identifier)
        list (, $punknown) = Arrays::partition($pointers, //
        fn ($n) => $n === '*' || CMatching::isTypeQualifier($n));

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
        return "CDeclaration:{$this->getType()}";
    }
}