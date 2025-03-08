<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Time2Split\Config\Configuration;
use Time2Split\Config\Configurations;
use Time2Split\PCP\Expression\Expressions;

final class ExpressionInterpolatorTest extends TestCase
{

    private static function emptyConfiguration(): Configuration
    {
        return Configurations::builder()
            ->setInterpolator(Expressions::interpolator([]))
            ->build();
    }
    private static function interpolatorTests(): array
    {
        $config = [
            'a' => 'aval',
            'b' => 'bval',
        ];
        $simple = [
            [['x' => 0], ['x' => 0], $config],
            [['x' => 'a'], ['x' => 'a'], $config],
            [['x' => '${a}'], ['x' => 'aval'], $config],
            [['x' => '<${a}>'], ['x' => '<aval>'], $config],
            [['x' => '${[1,2]}'], ['x' => [1, 2]], $config],
        ];

        return \array_merge(
            $simple,
        );
    }

    public static function interpolatorProvider(): \Traversable
    {
        return (function () {
            foreach (self::interpolatorTests() as $test)
                yield $test;
        })();
    }

    #[Test]
    #[DataProvider("interpolatorProvider")]
    public  function interpolator($assign, $expect, array $config): void
    {
        $config = self::emptyConfiguration()
            ->merge($config);

        foreach ($assign as $k => $v) {
            $config[$k] = $v;
        }

        foreach ($expect as $k => $v) {
            $this->assertSame($v, $config[$k]);
        }
    }
}
