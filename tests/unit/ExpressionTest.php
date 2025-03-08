<?php

declare(strict_types=1);

use Parsica\Parsica\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Time2Split\Config\Configurations;
use Time2Split\Help\Iterables;
use Time2Split\PCP\Expression\Expressions;
use Time2Split\PCP\Expression\Node\ConstNode;

final class ExpressionTest extends TestCase
{
    private static function testProvider(array $tests, ?callable $mapExpect = null)
    {
        $mapExpect ??= fn($e) => $e;
        foreach ($tests as [$expr, $expect]) {
            $header = "$expr";
            yield $header => [$expr, $mapExpect($expect)];
        }
    }

    private static function getParser(): Parser
    {
        return Expressions::expression([]);
    }

    private static function getConstExpressionTests(): array
    {
        $simple = [
            ['"A text"', 'A text'],
            ["'A text'", 'A text'],
            ["'it\\'s'", "it's"],
            ['"it\\"s"', 'it"s'],

            ['2', 2],

            ['!0', true],
            ['!1', false],
            ['!!0', false],
            ['!!1', true],

            ['[]', []],
            ['[1, !2]', [1, false]],
            ['[["text"]]', [['text']]],

            ['[]:0', false],
            ['[0]:0', true],
            ['[0]:1', false],
        ];
        $binary = [];
        foreach (Iterables::cartesianProductMerger([0, 1], [0, 1], ['&&', '||']) as  [$l, $r, $op]) {
            $s = "$l $op $r";
            $binary[] = [$s, eval("return $s;")];
        }
        $expr = [];
        foreach ($simple as [$e, $r]) {
            $expr[] = ["($e)", $r];
            $expr[] = [" ( $e ) ", $r];
        }
        $arrays = [];
        foreach ($simple as [$e, $r]) {
            $arrays[] = ["[$e]", [$r]];
            $arrays[] = [" [ $e ] ", [$r]];
        }
        $all = \array_merge(
            $simple,
            $binary,
            $expr,
            $arrays,
        );
        $interpolated = [];
        foreach ($all as [$e, $r]) {
            $interpolated[] = ["'\${{$e}}'", $r];
        }
        return \array_merge(
            $all,
            $interpolated
        );
    }

    public static function constExpressionProvider(): \Traversable
    {
        return self::testProvider(self::getConstExpressionTests());
    }

    #[Test]
    #[DataProvider("constExpressionProvider")]
    public  function constExpression(string $expr, $expect): void
    {
        $parser = self::getParser();
        $val = $parser->tryString($expr)->output();
        $this->assertInstanceOf(ConstNode::class, $val);
        $val = $val->getValue();
        $this->assertSame($expect, $val);
    }

    // ========================================================================

    private static function getAssignTests(): array
    {
        $append = [
            ['a=1 a:2', ['a' => [1, 2]]],
            ['a:1 a:2', ['a' => [1, 2]]],
            ['a=1 a:[2,3]', ['a' => [1, 2, 3]]],
        ];
        $const = [
            ['a', ['a' => true]],
            ['a.b', ['a' => ['b' =>  true]]],
        ];
        $expr =  [
            ['a=(1 && 0)', ['a' => false]],
            ['(1 && 0)', ['@expr' => false]],
            ['(1 && 0) ("hello")', ['@expr' => [false, "hello"]]],
        ];
        foreach (self::getConstExpressionTests() as [$e, $r]) {
            $const[] = ["a=$e", ['a' => $r]];
        }
        return \array_merge(
            $const,
            $expr,
            $append,
        );
    }

    public static function assignProvider(): \Traversable
    {
        return self::testProvider(self::getAssignTests());
    }

    #[Test]
    #[DataProvider("assignProvider")]
    public function assign(string $expr, array $expect): void
    {
        $config = Configurations::ofTree();
        $parser = Expressions::arguments([]);

        $parser->tryString($expr)->output()->get($config);

        $expect = Configurations::ofTree($expect);
        $this->assertSame($expect->toArray(), $config->toArray());
    }

    // ========================================================================

    private static function getVariableTests(): array
    {
        $conf = ['var' => 99];
        $bool = ['t' => 1, 'f' => 0];
        $text = ['a' => 'aval', 'b' => 'bval'];

        $const = [
            ['var', 99, $conf],
            ['"${var}"', 99, $conf],
            ['"<${var}>"', '<99>', $conf],
            ['"${a} ${b}"', 'aval bval', $text],
            ['t && t', true, $bool],
            ['t && f', false, $bool],
            ['f || f', false, $bool],
            ['t || f', true, $bool],
        ];
        return \array_merge(
            $const,
        );
    }

    public static function variableProvider(): \Traversable
    {
        return (function () {
            foreach (self::getVariableTests() as $test) {
                yield "{$test[0]}={$test[1]}" => $test;
            }
        })();
    }

    #[Test]
    #[DataProvider("variableProvider")]
    public function variable(string $expr,  $expect, array $config): void
    {
        $config = Configurations::ofTree()->merge($config);
        $parser = self::getParser();
        $val = $parser->tryString($expr)->output();

        $this->assertNotInstanceOf(ConstNode::class, $val);
        $this->assertSame($expect, $val->get($config));
    }

    // ========================================================================


    public static function theTestFilterProvider(): \Traversable
    {
        $data = [
            ['"lower"|upper()', 'LOWER', []],
            ['var|upper()', 'LOWER', ['var' => 'lower']],
        ];

        return (function () use ($data) {
            foreach ($data as $test) {
                yield "{$test[0]}={$test[1]}" => $test;
            }
        })();
    }

    private static function getParserWithFilter(): Parser
    {
        static $ret =  Expressions::expression([
            'upper' => fn(string $s) => \strtoupper($s)
        ]);
        return $ret;
    }

    #[DataProvider("theTestFilterProvider")]
    public function testFilter(string $expr, mixed $expect, array $config): void
    {
        $config = Configurations::ofTree($config);
        $parser = self::getParserWithFilter();
        $val = $parser->tryString($expr)->output()->get($config);
        $this->assertSame($expect, $val);
    }
}
