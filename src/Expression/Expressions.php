<?php
namespace Time2Split\PCP\Expression;

use Time2Split\Config\Configuration;
use Time2Split\Config\Interpolator;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Optional;
use Time2Split\PCP\Expression\Node\Node;
use Parsica\Parsica\Parser;
use Parsica\Parsica\ParseResult;
use Parsica\Parsica\ParserHasFailed;
use function Parsica\Parsica\ {
    char,
    string,
    between,
    alphaChar,
    alphaNumChar,
    controlChar,
    printChar,
    nothing,
    keepSecond,
    skipHSpace,
    skipSpace,
    atLeastOne,
    recursive,
    choice,
    either,
    some,
    many,
    satisfy,
    notPred,
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

    private static function boolNode(bool $b): Node
    {
        return new class($b) implements Node {

            function __construct(public readonly bool $b)
            {}

            public function get(Configuration $config): bool
            {
                return $this->b;
            }
        };
    }

    private static function stringNode(string $s): Node
    {
        return new class($s) implements Node {

            function __construct(public readonly string $text)
            {}

            public function get(Configuration $config): string
            {
                return $this->text;
            }
        };
    }

    private static function configValueNode(string $key): Node
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

    private static function unaryNode(string $op, Node $node): UnaryNode
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

    private static function binaryNode(string $op, Node $left, Node $right): BinaryNode
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

    private static function assignmentNode(string $op, Node $left, Node $right): BinaryNode
    {
        return match ($op) {
            '=' => new class("set$op", $left, $right) extends BinaryNode {

                public function get(Configuration $config): mixed
                {
                    return $config[$this->left->get($config)] = $this->right->get($config);
                }
            },
            // append
            ':' => new class("set$op", $left, $right) extends BinaryNode {

                public function get(Configuration $config): mixed
                {
                    $key = $this->left->get($config);
                    $val = $this->right->get($config);
                    $cval = $config[$key];

                    if (\is_array($cval)) {

                        if (\is_array($val)) {

                            foreach ($val as $v)
                                $cval[] = $v;
                        } else
                            $cval[] = $val;
                    } elseif (isset($cval))
                        $cval = [
                            $cval,
                            $val
                        ];
                    else
                        $cval = $val;

                    return $config[$key] = $cval;
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

    static function assignmentsNode(array $array): Node
    {
        return new class($array) implements Node {

            function __construct(private readonly array $array)
            {}

            public function get(Configuration $config): mixed
            {
                foreach ($this->array as $v)
                    $v->get($config);
                return null;
            }
        };
    }

    // ========================================================================
    // Parser utilities
    private static function dump($e)
    {
        var_dump($e);
        return $e;
    }

    private static function zeroOrMore(Parser $parser): Parser
    {
        return optional(atLeastOne($parser));
    }

    private static function skipSpaces(Parser $parser): Parser
    {
        return keepSecond(skipHSpace(), $parser);
    }

    private static function parenthesis(Parser $parser): Parser
    {
        return between(self::skipSpaces(char('(')), self::skipSpaces(char(')')), $parser);
    }

    private static function string(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $makeString = fn ($delim) => between(char($delim), char($delim), //
        either( //
        atLeastOne(either(keepSecond(char('\\'), anySingle()), anySingleBut($delim))), //
        nothing()));

        $string = choice($makeString('"'), $makeString("'"));
        return $ret = $string->map(fn ($s) => self::stringNode((string) $s));
    }

    private static function variable(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $firstCharKey = either(char('_'), alphaChar());
        $oneKeyChar = either(alphaNumChar(), char('_'));
        $oneKey = atLeastOne($oneKeyChar);

        $pathSequence = self::zeroOrMore(char('.')->append($oneKey));
        $firstkey = $firstCharKey->append(optional($oneKey));

        $path = [
            $firstkey->append($pathSequence),
            char('$')->sequence(optional(choice(...[
                $oneKey->append($pathSequence),
                self::string()->map(fn ($node) => $node->text)
            ]))->map(\strval(...)))
        ];
        return $ret = either(...$path);
    }

    private static function binaryOperator(string $op)
    {
        $pop = \strlen($op) > 1 ? string($op) : char($op);
        return binaryOperator(self::skipSpaces($pop), fn (Node $l, Node $r) => self::binaryNode($op, $l, $r));
    }

    private static function unaryOperator(string $op)
    {
        $pop = \strlen($op) > 1 ? string($op) : char($op);
        return unaryOperator(self::skipSpaces($pop), fn (Node $node) => self::unaryNode($op, $node));
    }

    // ========================================================================
    private static function expression(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $makeBin = self::binaryOperator(...);
        $makePrefix = self::unaryOperator(...);

        $expr = recursive();
        $variable = self::variable()->map(fn ($k) => Expressions::configValueNode($k));
        $primary = choice(self::parenthesis($expr), self::string(), $variable);
        $primary = self::skipSpaces($primary);
        $expr->recurse(expression($primary, [
            prefix($makePrefix('!!')),
            prefix($makePrefix('!')),
            nonAssoc($makeBin(':')),
            leftAssoc($makeBin('&&')),
            leftAssoc($makeBin('||'))
        ]));
        return $ret = $expr;
    }

    public static function inText(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $expr = between(string('${'), self::skipSpaces(char('}')), self::expression());
        $text = atLeastOne(anySingleBut('$'));
        return $ret = some(choice($expr, $text));
    }

    public static function arguments(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $makeOp = fn ($op) => self::skipSpaces(char($op));
        $toArray = fn ($s) => [
            $s
        ];

        $expr = between(string('${'), self::skipSpaces(char('}')), self::expression());
        $string = self::inText();
        $value = [
            self::string()->map(fn ($s) => $string->tryString($s->text)
                ->output())
                ->map(self::arrayNode(...)),
            atLeastOne(satisfy(notPred(\ctype_space(...))))->map(self::stringNode(...)),
            $expr
        ];
        $value = choice(...$value)->map($toArray);
        $value = self::skipSpaces($value);

        $var = self::variable()->map(self::stringNode(...))->map($toArray);
        $var = self::skipSpaces($var);

        $makeAssign = fn ($op) => $var->append($makeOp($op)->sequence($value))
            ->map(fn ($res) => self::assignmentNode($op, $res[0], $res[1]));

        $assignment = [
            $makeAssign('='),
            $makeAssign(':'),
            $var->map(fn ($res) => self::assignmentNode('=', $res[0], self::boolNode(true)))
        ];
        $assignment = choice(...$assignment);
        $assignment = self::skipSpaces($assignment);

        return $ret = many($assignment)->thenIgnore(skipSpace())
            ->thenEof()
            ->map(self::assignmentsNode(...));
    }

    // ========================================================================
    public static function interpolator(): Interpolator
    {
        return new class() implements Interpolator {

            public function compile($value): Optional
            {
                if (! is_string($value))
                    return Optional::empty();

                $parser = Expressions::inText();

                try {
                    $res = $parser->tryString($value);
                } catch (ParserHasFailed $e) {
                    return Optional::empty();
                }
                return self::makeInTextResult($res, $value);
            }

            private static function makeInTextResult(ParseResult $res, string $text): Optional
            {
                $res = $res->output();

                if (1 === \count($res)) {

                    // The parsed result is the input
                    if ($text === $res[0])
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