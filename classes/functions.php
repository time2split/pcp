<?php
if (! function_exists('array_is_list')) {

    function array_is_list(array $array)
    {
        return \array_keys($array) === \range(0, \count($array) - 1);
    }
}

if (! function_exists('str_starts_with')) {

    function str_starts_with(string $haystack, string $needle)
    {
        return strpos($haystack, $needle) === 0;
    }
}

function error(string ...$params)
{
    foreach ($params as $p)
        fwrite(STDERR, implode('', $params));
}

function error_dump(...$params)
{
    foreach ($params as $p)
        fwrite(STDERR, print_r($p, true) . "\n");
}

function error_dump_exit(...$params)
{
    error_dump(...$params);
    exit();
}

function is_array_list($array): bool
{
    return \is_array($array) && \array_is_list($array);
}

function str_format(string $s, array $vars): string
{
    return \str_replace(\array_map(fn ($k) => "%$k", \array_keys($vars)), \array_values($vars), $s);
}

function str_empty(string $s): bool
{
    return strlen($s) === 0;
}

function srange($min, $max): string
{
    if ($min === $max)
        return "$min";

    return "$min,$max";
}

function removePrefix(string $s, string $prefix): string
{
    if (0 === \strpos($s, $prefix))
        return \substr($s, \strlen($prefix));

    return $s;
}

function in_range($val, $min, $max)
{
    return $min <= $val && $val <= $max;
}

function mapArgKey_replace($search, $replace, ?callable $onCondition = null): callable
{
    return fn ($k) => ($onCondition ? $onCondition($k) : true) ? //
    \str_replace($search, $replace, $k) : //
    $k;
}

function mapArgKey_default(?callable $onCondition = null): callable
{
    return \mapArgKey_replace('.', '_', fn ($k) => ! \is_int($k) && ($onCondition ? $onCondition($k) : true));
}

function updateObject(array $args, object &$obj, string $k_prefix = '')
{
    foreach ($args as $k => $v) {
        $k = \str_replace('.', '_', $k);
        $obj->{"$k_prefix$k"} = $v;
    }
}

