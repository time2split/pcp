<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Time2Split\PCP\Diff\Algorithm\Myers;
use Time2Split\PCP\Diff\Output\DiffInstructionType;

final class MyersTest extends TestCase
{
    private static function getTypeChar(DiffInstructionType $type)
    {
        return match ($type) {
            DiffInstructionType::Drop => '-',
            DiffInstructionType::Keep => '',
            DiffInstructionType::Insert => '+',
        };
    }

    public static function textProvider(): \Traversable
    {
        $dataset = [
            [
                'ABCABBA',
                'CBABAC',
                '-A-BC+BAB-BA+C'
            ],
            [
                'ABCABB',
                'CBABAC',
                '-A-BC+BAB-B+A+C'
            ],
            [
                'ABCABBA',
                'CBABA',
                '-A-BC+BAB-BA'
            ]
        ];
        return (function () use ($dataset) {
            $i = 1;
            foreach ($dataset as $data) {
                $text = "#$i $data[0]:$data[1]";
                yield "$text" => $data;
                $i++;
            }
        })();
    }

    #[DataProvider("textProvider")]
    public function testText(string $a, string $b, string $expect): void
    {
        $a = \str_split($a);
        $b = \str_split($b);
        $editScript = Myers::diff($a, $b);

        $res = "";

        foreach ($editScript as $i)
            $res .= self::getTypeChar($i->type) . "$i->item";

        $this->assertSame($expect, $res);
    }
}
