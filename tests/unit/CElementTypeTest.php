<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Time2Split\Help\Tests\DataProvider\Provided;
use Time2Split\PCP\C\Element\CElementType;

final class CElementTypeTest extends TestCase
{
    private const AllowedTypes = [
        [],
        [CElementType::CPP],
        [CElementType::CPP, CElementType::Definition],
        [CElementType::Variable, CElementType::Declaration],
        [CElementType::Function, CElementType::Declaration],
        [CElementType::Function, CElementType::Definition],
    ];

    private static function fun_of_types(): array
    {
        $ret = [];

        foreach (self::AllowedTypes as $types) {
            $head = \implode('.', \array_map(fn($t) => $t->name, $types));
            $ret[] = new Provided($head, [$types]);
        }
        return $ret;
    }

    public static function fun_ofProvider(): iterable
    {
        return Provided::merge(self::fun_of_types());
    }


    #[DataProvider("fun_ofProvider")]
    public function testFun_of(array $types): void
    {
        $set = CElementType::of(...$types);
        $this->assertSame($types, \iterator_to_array($set));

        $setb = CElementType::of(...$types);
        $this->assertSame($set, $setb);
    }

    public function testFun_ofException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CElementType::of(CElementType::CPP, CElementType::Function);
    }
}
