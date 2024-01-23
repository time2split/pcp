<?php
namespace Time2Split\PCP\Expression;

use Time2Split\Config\Configuration;
use Time2Split\Config\Interpolator;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Optional;
use Time2Split\PCP\Expression\Node\Node;
use Parsica\Parsica\Parser;
use Parsica\Parsica\ParserHasFailed;
use function Parsica\Parsica\ {
    char,
    string,
    between,
    alphaChar,
    alphaNumChar,
    nothing,
    keepSecond,
    skipHSpace,
    atLeastOne,
    recursive,
    choice,
    either,
    some,
    many,
    append,
    optional,
    anySingle,
    anySingleBut
};
use function Parsica\Parsica\Expression\ {
    binaryOperator,
    unaryOperator,
    prefix,
    expression,
    leftAssoc,
    rightAssoc,
    nonAssoc
};
use Time2Split\PCP\Expression\Node\BinaryNode;
use Time2Split\PCP\Expression\Node\UnaryNode;

final class Expressions
{
    use NotInstanciable;

    static function stringNode(string $s): Node
    {
        return new class($s) implements Node {

            function __construct(private readonly string $text)
            {}

            public function get(Configuration $config): mixed
            {
                return $this->text;
            }
        };
    }

    static function configValueNode(string $key): Node
    {
        return new class($key) implements Node {

            function __construct(private readonly string $key)
            {}

            public function get(Configuration $config): mixed
            {
                return $config[$this->key];
            }
        };
    }

    static function unaryNode(string $op, Node $node): UnaryNode
    {
        return match ($op) {
            '!!' => new class($op, $node) extends UnaryNode {

                public function get(Configuration $config): bool
                {
                    return (bool) $this->node->get($config);
                }
            },
            '!' => new class($op, $node) extends UnaryNode {

                public function get(Configuration $config): bool
                {
                    return ! $this->node->get($config);
                }
            }
        };
    }

    static function binaryNode(string $op, Node $left, Node $right): BinaryNode
    {
        return match ($op) {
            '&&' => new class($op, $left, $right) extends BinaryNode {

                public function get(Configuration $config): bool
                {
                    return $this->left->get($config) && $this->right->get($config);
                }
            },
            '||' => new class($op, $left, $right) extends BinaryNode {

                public function get(Configuration $config): bool
                {
                    return $this->left->get($config) || $this->right->get($config);
                }
            },
            ':' => new class($op, $left, $right) extends BinaryNode {

                public function get(Configuration $config): bool
                {
                    $l = $this->left->get($config);
                    $r = $this->right->get($config);

                    if (\is_array($l))
                        return \in_array($r, $l);

                    return $l == $r;
                }
            }
        };
    }

    static function arrayNode(array $array): Node
    {
        return new class($array) implements Node {

            function __construct(private readonly array $array)
            {}

            public function get(Configuration $config): mixed
            {
                $ret = [];

                foreach ($this->array as $v) {
                    if ($v instanceof Node)
                        $ret[] = $v->get($config);
                    else
                        $ret[] = $v;
                }
                return \implode('', $ret);
            }
        };
    }

    // ========================================================================
    public static function interpolator(): Interpolator
    {
        return new class() implements Interpolator {

            public function compile($value): Optional
            {
                if (! is_string($value))
                    return Optional::empty();

                // Helpers
                $zeroOrMore = fn ($parser) => optional(atLeastOne($parser));
                $nospac = fn ($parser) => keepSecond(skipHSpace(), $parser);
                $parens = fn ($parser) => $nospac(between($nospac(char('(')), $nospac(char(')')), $parser));

                // Variable interpolation
                $key = choice(char('_'), alphaChar())->append($zeroOrMore(alphaNumChar()));
                $path = $key->append(optional(atLeastOne(char('.')->append($key))))
                    ->map(fn ($k) => Expressions::configValueNode($k));

                // $string = between(char('"'), char('"'), $zeroOrMore(either(keepSecond(char('\\'), anySingle()), anySingle())));
                $makeString = fn ($delim) => between(char($delim), char($delim), //
                either( //
                atLeastOne(either(keepSecond(char('\\'), anySingle()), anySingleBut($delim))), //
                nothing()));

                $string = choice($makeString('"'), $makeString("'"));
                $string = $string->map(fn ($s) => Expressions::stringNode((string) $s));

                // Expression
                $makeBin = fn ($op) => binaryOperator($nospac(string($op)), fn (Node $l, Node $r) => Expressions::binaryNode($op, $l, $r));
                $makePrefix = fn ($op) => unaryOperator($nospac(string($op)), fn (Node $node) => Expressions::unaryNode($op, $node));

                $expr = recursive();
                $primary = choice($parens($expr), $string, $path);
                $primary = $nospac($primary);
                $expr->recurse(expression($primary, [
                    prefix($makePrefix('!!')),
                    prefix($makePrefix('!')),
                    nonAssoc($makeBin(':')),
                    leftAssoc($makeBin('&&')),
                    leftAssoc($makeBin('||'))
                ]));

                // Expression delimiters
                $root = between(string('${'), $nospac(char('}')), $expr);
                $text = atLeastOne(anySingleBut('$'));
                $parser = some(choice($root, $text));

                try {
                    $res = $parser->tryString($value);
                } catch (ParserHasFailed $e) {
                    return Optional::empty();
                }
                $res = $res->output();

                if (1 === \count($res)) {

                    // The parsed result is the input
                    if ($value === $res[0])
                        return Optional::empty();

                    $res = $res[0];
                } else {
                    $res = Expressions::arrayNode($res);
                }
                return Optional::of($res);
            }

            public function execute($compilation, Configuration $config): mixed
            {
                return self::_execute($compilation, $config);
            }

            private static function _execute(Node $compilation, Configuration $config)
            {
                return $compilation->get($config);
            }
        };
    }
}