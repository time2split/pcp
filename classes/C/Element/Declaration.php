<?php
namespace C\Element;

use C\ReaderElement;
use C\Matching;
use C\DeclarationGroup;
use C\DeclarationType;

final class Declaration extends ReaderElement
{
    use ElementTypeTrait;

    private array $uinfos;

    private function __construct(array $elements)
    {
        parent::__construct($elements);
        $this->uinfos = [];
    }

    public static function fromReaderElements(array $element)
    {
        return new Declaration($element);
    }

    public function sendTo(\Action\IAction $action): bool
    {
        return $action->deliver($this);
    }

    public function getGroup(): DeclarationGroup
    {
        return $this->elements['group'];
    }

    public function getType(): DeclarationType
    {
        return $this->elements['type'];
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
        $unknown = \array_filter($specifiers, fn ($n) => ! Matching::isSpecifier($n));
        $typeSpecifiers = \array_filter($specifiers, fn ($n) => Matching::isTypeSpecifier($n));

        $pointers = \array_slice($element['items'], $nbSpecifiers);
        $pointers = \array_filter($pointers); // Avoid null value (generated identifier)
        list ($p, $punknown) = \Help\Arrays::partition($pointers, //
        fn ($n) => $n === '*' || Matching::isTypeQualifier($n));

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
}