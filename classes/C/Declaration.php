<?php
namespace C;

class Declaration implements \Action\IActionMessage
{

    private array $e;

    private array $uinfos;

    private function __construct(array $e)
    {
        $this->e = $e;
        $this->uinfos = [];
    }

    public static function from(array $element)
    {
        return new Declaration($element);
    }

    public function sendTo(\Action\IAction $action): bool
    {
        return $action->deliver($this);
    }

    public function getGroup(): DeclarationGroup
    {
        return $this->e['group'];
    }

    public function getType(): DeclarationType
    {
        return $this->e['type'];
    }

    // ========================================================================
    public function getElement(): array
    {
        return $this->e;
    }

    public function getUnknownInfos(): array
    {
        if (! empty($this->uinfos))
            return $this->uinfos;

        return $this->uinfos = self::makeUnknownInfos($this->e);
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