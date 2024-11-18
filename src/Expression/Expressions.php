<?php

declare(strict_types=1);

namespace Time2Split\PCP\Expression;

use Time2Split\Config\Configuration;
use Time2Split\Config\Interpolator;
use Time2Split\Help\Classes\NotInstanciable;
use Time2Split\Help\Optional;
use Time2Split\PCP\Expression\Node\Node;
use Parsica\Parsica\Parser;
use Parsica\Parsica\ParserHasFailed;
use Time2Split\Help\Arrays;
use Time2Split\Help\Set;
use Time2Split\Help\Sets;

use function Parsica\Parsica\{
    char,
    string,
    between,
    alphaChar,
    alphaNumChar,
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
    optional,
    anySingle,
    anySingleBut,
    assemble,
    digitChar,
    noneOf,
};
use function Parsica\Parsica\Expression\{
    binaryOperator,
    unaryOperator,
    prefix,
    expression,
    leftAssoc,
    nonAssoc
};
use Time2Split\PCP\Expression\Node\BinaryNode;
use Time2Split\PCP\Expression\Node\UnaryNode;
use Time2Split\PCP\Expression\Node\ConfigValueNode;
use Time2Split\PCP\Expression\Node\StringNode;
use Time2Split\PCP\Expression\Node\BoolNode;
use Time2Split\PCP\Expression\Node\AssignmentNode;
use Time2Split\PCP\Expression\Node\ConstArrayNode;
use Time2Split\PCP\Expression\Node\ConstNode;
use Time2Split\PCP\Expression\Node\IntegerNode;
use Time2Split\PCP\Help\HelpSets;

final class Expressions
{
    use NotInstanciable;

    private static function boolNode(bool $b): BoolNode
    {
        return new class($b) extends BoolNode {

            function __construct(public readonly bool $b) {}

            public function getValue(): bool
            {
                return $this->b;
            }
        };
    }

    private static function stringNode(?string $s): StringNode
    {
        return new class((string) $s) extends StringNode {

            function __construct(public readonly string $text) {}

            public function getValue(): string
            {
                return $this->text;
            }
        };
    }

    private static function integerNode(int $s): IntegerNode
    {
        return new class($s) extends IntegerNode {

            function __construct(public readonly int $val) {}

            public function getValue(): int
            {
                return $this->val;
            }
        };
    }

    private static function constArrayNode(array $s): ConstArrayNode
    {
        return new class($s) extends ConstArrayNode {

            function __construct(public readonly array $val) {}

            public function getValue(): array
            {
                return $this->val;
            }
        };
    }

    // ========================================================================

    private static function configValueNode(string $key): ConfigValueNode
    {
        return new class($key) extends ConfigValueNode {

            function __construct(private readonly string $key) {}

            public function get(Configuration $config): mixed
            {
                return $config[$this->key];
            }

            public function __toString()
            {
                return "\${{$this->key}}";
            }
        };
    }

    /**
     * @internal
     */
    public static function wrapNode(Node $node, callable $fget, string $op = 'wrap:')
    {
        return new class($node, $fget, $op) extends UnaryNode
        {
            private $fget;

            public function __construct(Node $node, callable $fget, string $op)
            {
                parent::__construct($op, $node);
                $this->fget = $fget;
            }

            public function get(Configuration $config): mixed
            {
                return ($this->fget)($this->node, $config);
            }
        };
    }

    private static function unaryNode(string $op, Node $node): UnaryNode|ConstNode
    {
        if ($node instanceof ConstNode) {
            $b = (bool) $node->getValue();

            return match ($op) {
                '!!' => self::boolNode($b),
                '!' => self::boolNode(!$b),
            };
        }
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

    private static function binaryNode(string $op, Node $left, Node $right): BinaryNode|ConstNode
    {
        if ($left instanceof ConstNode && $right instanceof ConstNode) {
            $left = $left->getValue();
            $right = $right->getValue();

            return self::boolNode(
                match ($op) {
                    '&&' => $left && $right,
                    '||' => $left || $right,
                    ':' => self::_op_in($left, $right),
                }
            );
        }
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
                    return Expressions::_op_in($this->left->get($config), $this->right->get($config));
                }
            }
        };
    }

    /**
     *  @internal
     */
    public static function _op_in($left, $right): bool
    {
        $left = Expressions::_ensureSet($left);
        $right = Expressions::_ensureSet($right);
        return HelpSets::includedIn($right, $left);
    }

    /**
     *  @internal
     */
    public static function _ensureSet($v): Set
    {
        if ($v instanceof Set)
            return $v;
        else
            return Sets::arrayKeys()->setMore(...Arrays::ensureArray($v));
    }

    private static function assignmentNode(string $op, Node $left, Node $right): BinaryNode
    {
        return match ($op) {
            '=' => new class("set$op", $left, $right) extends AssignmentNode {

                public function get(Configuration $config): mixed
                {
                    $key = $this->left->get($config);
                    $val = $this->dereferenceValue($config, $this->right);
                    return $config[$key] = $val;
                }
            },
            // append
            ':' => new class("set$op", $left, $right) extends AssignmentNode {

                public function get(Configuration $config): mixed
                {
                    $key = $this->left->get($config);
                    $val = $this->right;
                    $val = $this->dereferenceValue($config, $val);
                    $cval = $config[$key];

                    if (!isset($config[$key]))
                        $config[$key] = $val;
                    else {
                        if (!\is_array($cval))
                            $cval = [$cval];
                        if (!\is_array($val))
                            $val = (array)$val;

                        $config[$key] = [
                            ...$cval,
                            ...$val
                        ];
                    }
                    return $val;
                }
            }
        };
    }

    /**
     * @internal
     */
    public static function arrayNode(?array $array): Node
    {
        $array ??= [];
        $isConst = true;

        foreach ($array as &$v) {

            if ($v instanceof ConstNode)
                $v = $v->getValue();
            else
                $isConst = false;
        }
        unset($v);

        if ($isConst)
            return self::constArrayNode($array);
        else
            return self::noConstArrayNode($array);
    }

    /**
     * @internal
     */
    public static function noConstArrayNode(array $array)
    {
        return new class($array) implements Node {

            function __construct(private readonly array $array) {}

            public function get(Configuration $config): mixed
            {
                $ret = [];

                foreach ($this->array as $v) {
                    if ($v instanceof Node)
                        $ret[] = $v->get($config);
                    else
                        $ret[] = $v;
                }
                return $ret;
            }

            public function __toString()
            {
                $ret = [];

                foreach ($this->array as $v) {
                    if ($v instanceof Node)
                        $ret[] = "\${ $v }";
                    else
                        $ret[] =  $v;
                }
                return print_r($ret, true);
            }
        };
    }

    private static function arrayNodeOrString(array $array): Node
    {
        if (\count($array) === 1 && \is_string($array[0]))
            return self::stringNode($array[0]);

        return self::arrayNode($array);
    }

    private static function assignmentsNode(array $array): Node
    {
        return new class($array) implements Node {

            function __construct(private readonly array $array) {}

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

    private static function delimiter(string $delim): Parser
    {
        return self::skipSpaces(
            \strlen($delim) > 1 ? string($delim) : char($delim)
        );
    }

    private static function parenthesis(Parser $parser): Parser
    {
        return self::between('(', ')', $parser);
    }

    private static function between(string $chara, string $charb, Parser $parser): Parser
    {
        return between(
            self::delimiter($chara),
            self::delimiter($charb),
            $parser
        );
    }
    // ========================================================================

    private static function array(Parser $expression): Parser
    {
        $expression = $expression->map(Arrays::ensureArray(...));
        $exprList = assemble(
            $expression,
            self::zeroOrMore(
                keepSecond(self::delimiter(','), $expression)
            )->map(function ($s) {
                return $s === null ?
                    [] : $s;
            })
        );

        return self::between('[', ']', choice(
            $exprList,
            nothing(),
        ))->map(fn($s) => self::arrayNode($s));
    }

    private static function stringEscape(): Parser
    {
        return keepSecond(char('\\'), anySingle());
    }

    private static function stringContents(string $delim): Parser
    {
        $string = fn($delim) => atLeastOne(
            either(self::stringEscape(), anySingleBut($delim))
        );
        return $string($delim);
    }

    private static function string(): Parser
    {
        $makeString = fn($delim) => between(
            char($delim),
            char($delim),
            either(self::stringContents($delim), nothing())
        );
        return choice(
            $makeString('"'),
            $makeString("'")
        )->map(fn($s) => self::stringNode((string) $s));
    }

    private static function integer(): Parser
    {
        return atLeastOne(digitChar())
            ->map((fn($s) => self::integerNode((int)$s)));
    }

    private static function stringChar(Parser ...$parsers)
    {
        if (empty($parsers))
            $parsers = [anySingle()];

        return choice(self::stringEscape(), ...$parsers);
    }

    /**
     * @internal
     */
    public static function stringWithInterpolationContents(Parser $expr, string ...$delims): Parser
    {
        $inlineExpr = self::between('${', '}', $expr);
        $char = atLeastOne(self::stringChar(noneOf([...$delims, '$'])));
        return some(choice(
            atLeastOne($char),
            $inlineExpr,
            char('$')->append(atLeastOne($char)),
        ));
    }
    private static function stringWithInterpolation(Parser $expr): Parser
    {
        $makeString = fn($delim) => between(
            char($delim),
            char($delim),
            either(self::stringWithInterpolationContents($expr, $delim), nothing())
        );
        return choice(
            $makeString('"'),
            $makeString("'"),
        )
            ->map(fn($e) => (array)$e)
            ->map(self::stringWithInterpolationResult(...))
        ;
    }

    /**
     * @internal
     */
    public static function stringWithInterpolationResult(array $res): Node
    {
        $c = \count($res);

        if (0 === $c)
            return self::stringNode("");
        if (1 === $c) {
            $res = $res[0];

            if (\is_string($res))
                return self::stringNode($res);
            else
                return $res;
        } else {
            $res = Expressions::arrayNode($res);

            if ($res instanceof ConstNode)
                return self::stringNode(implode($res->getValue()));

            return self::wrapNode($res, fn(Node $s, Configuration $config) => \implode($s->get($config)));
        }
    }


    // ========================================================================

    private static function variable(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $firstCharKey = choice(char('_'), char('@'), alphaChar());
        $oneKeyChar = either(alphaNumChar(), char('_'));
        $oneKey = atLeastOne($oneKeyChar);

        $pathSequence = self::zeroOrMore(char('.')->append($oneKey));
        $firstkey = $firstCharKey->append(optional($oneKey));

        return $firstkey->append($pathSequence);
    }

    private static function binaryOperator(string $op)
    {
        $pop = \strlen($op) > 1 ? string($op) : char($op);
        return binaryOperator(
            self::skipSpaces($pop),
            fn(Node $l, Node $r) => self::binaryNode($op, $l, $r)
        );
    }

    private static function unaryOperator(string $op)
    {
        $pop = \strlen($op) > 1 ? string($op) : char($op);
        return unaryOperator(
            self::skipSpaces($pop),
            fn(Node $node) => self::unaryNode($op, $node)
        );
    }

    // ========================================================================
    public static function expression(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $makeBin = self::binaryOperator(...);
        $makePrefix = self::unaryOperator(...);

        $expr = recursive();
        $variable = self::variable()
            ->map(Expressions::configValueNode(...));
        $primary = choice(
            self::parenthesis($expr),
            self::array($expr),
            self::stringWithInterpolation($expr),
            $variable,
            self::integer(),
        );
        $primary = self::skipSpaces($primary);

        $expr->recurse(
            expression($primary, [
                prefix($makePrefix('!')),
                prefix($makePrefix('!!')),
                nonAssoc($makeBin(':')),
                leftAssoc($makeBin('&&')),
                leftAssoc($makeBin('||'))
            ])
        );
        return $ret = $expr;
    }

    public static function arguments(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $toArray = fn($s) => [$s];

        $expr = self::expression();
        $value = self::skipSpaces($expr)
            ->map($toArray);

        $var = self::variable()->map(self::stringNode(...))->map($toArray);
        $var = self::skipSpaces($var);

        $makeAssign = fn($op) => $var
            ->append(self::delimiter($op)->sequence($value))
            ->map(fn($res) => self::assignmentNode($op, $res[0], $res[1]));

        $assignment = [
            $makeAssign('='),
            $makeAssign(':'),
            self::parenthesis($expr)
                ->map($toArray)
                ->map(fn($res) => self::assignmentNode(':', Expressions::stringNode('@expr'), $res[0])),
            $var->map(fn($res) => self::assignmentNode('=', $res[0], self::boolNode(true)))
        ];
        $assignment = choice(...$assignment);
        $assignment = self::skipSpaces($assignment);

        return $ret = many($assignment)->thenIgnore(skipSpace())
            ->thenEof()
            ->map(self::assignmentsNode(...));
    }

    // ========================================================================

    /**
     * @internal
     */
    public static function _configValue(): Parser
    {
        static $ret;

        if (isset($ret))
            return $ret;

        $inlineExpr = self::between('${', '}', self::expression());
        $parser = some(either($inlineExpr, atLeastOne(anySingleBut('$'))));
        return $ret = $parser;
    }

    public static function interpolator(): Interpolator
    {
        return new class() implements Interpolator {

            public function compile($value): Optional
            {
                // Set directly a compiled node
                if ($value instanceof Node)
                    return Optional::of($value);
                if (! is_string($value))
                    return Optional::empty();

                $parser = Expressions::stringWithInterpolationContents(Expressions::expression());

                try {
                    $res = $parser->tryString($value);
                } catch (ParserHasFailed $e) {
                    return Optional::empty();
                }
                return Optional::of(
                    Expressions::stringWithInterpolationResult($res->output())
                );
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
