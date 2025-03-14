<?php

declare(strict_types=1);

namespace Time2Split\PCP\Diff\Algorithm;

use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\PCP\Diff\Output\DiffInstructions;
use Time2Split\PCP\Diff\Output\DiffInstructionType;

final class Myers
{
    use NotInstanciable;

    private static function traceToDiffInstructions(array $trace, array $a, array $b): iterable
    {
        $x = 0;
        $y = 0;

        foreach ($trace as [$tx, $ty]) {

            if ($tx - $ty > $x - $y) {
                do {
                    yield DiffInstructions::createInstruction(DiffInstructionType::Drop, $a[$x]);
                    $x++;
                } while ($tx - $ty > $x - $y);
            } else {

                while ($tx - $ty < $x - $y) {
                    yield DiffInstructions::createInstruction(DiffInstructionType::Insert, $b[$y]);
                    $y++;
                }
            }
            while ($x < $tx) {
                yield DiffInstructions::createInstruction(DiffInstructionType::Keep, $a[$x]);
                $x++;
                $y++;
            }
        }
    }

    private static function backtrack(array $vstack, int $x, int $y): array
    {
        $trace = [];
        $d = \max($x, $y);

        while (!empty($vstack)) {
            $v = \array_pop($vstack);
            $k = $x - $y;
            $trace[] = [$x, $y];

            if ($k === -$d || $k !== $d && $v[$k - 1] < $v[$k + 1])
                $k_prev = $k + 1;
            else
                $k_prev = $k - 1;

            $x = $v[$k_prev];
            $y = $x - $k_prev;
            $d--;
        }
        return \array_reverse($trace);
    }

    private static function _diff(array $a, array $b, ?callable $equals = null): array
    {
        $equals ??= fn($a, $b) => $a === $b;

        $n = \count($a);
        $m = \count($b);
        $max = $m + $n;

        $vstack = [];
        $v = \array_fill(-$m, $max + 1, 0);

        for ($d = 0; $d <= $max; $d++) {

            for ($k = -$d; $k <= $d; $k += 2) {

                if ($k === -$d || $k !== $d && $v[$k - 1] < $v[$k + 1])
                    $x = $v[$k + 1];
                else
                    $x = $v[$k - 1] + 1;

                $y = $x - $k;

                while ($x < $n && $y < $m && $equals($a[$x], $b[$y])) {
                    $x++;
                    $y++;
                }
                $v[$k] = $x;

                if ($x >= $n && $y >= $m)
                    break 2;
            }
            $vstack[] = $v;
        }
        return $vstack;
    }

    /**
     * @return \Generator<int,DiffInstruction>
     */
    public static function diff(iterable $a, iterable $b, ?callable $equals = null): \Generator
    {
        $a = \iterator_to_array($a);
        $b = \iterator_to_array($b);

        $vstack = self::_diff($a, $b, $equals);
        $trace = self::backtrack($vstack, \count($a), \count($b));
        return self::traceToDiffInstructions($trace, $a, $b);
    }

    /**
     * @return array<int,DiffInstruction>
     */
    public static function diffList(iterable $a, iterable $b, ?callable $equals = null): array
    {
        return \iterator_to_array(self::diff($a, $b, $equals));
    }
}
