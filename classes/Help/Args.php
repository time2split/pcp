<?php
namespace Help;

final class Args
{

    private function __construct()
    {
        throw new \Error();
    }

    public static function parseString(string $args): array
    {
        return self::parseArgv(\preg_split('#\s+#', $args));
    }

    public static function parseArgv(array $argv): array
    {
        return self::parseArgvShift($argv);
    }

    public static function parseArgvShift(array &$argv, string $endArg = ''): array
    {
        $ret = [];
        while (null !== ($arg = \array_shift($argv))) {

            if ($arg === $endArg)
                break;
            if ($arg[0] === '+' || $arg[0] === '-') {
                $sign = $arg[0];
                $arg = \substr($arg, 1);
                list ($name, $val) = self::parseKeyValueArg($argv, $arg);

                if (\is_int($name)) {
                    $name = $val;
                    $val = ($sign === '+');
                }
            } else {
                list ($name, $val) = self::parseKeyValueArg($argv, $arg);
            }

            if (\is_int($name))
                $ret[] = $val;
            else
                $ret[$name] = $val;
        }
        return $ret;
    }

    private static function parseKeyValueArg(array &$argv, string $currentArg): array
    {
        if (false !== \strpos($currentArg, '=')) {
            list ($name, $val) = \explode('=', $currentArg, 2);
            return [
                $name,
                $val
            ];
        } elseif ($currentArg[\strlen($currentArg) - 1] === ':') {
            return [
                \substr($currentArg, 0, - 1),
                \array_shift($argv)
            ];
        } else
            return [
                0,
                $currentArg
            ];
    }

    public static function argPrefixed(array $args, string $prefix)
    {
        $ret = [];

        foreach ($args as $arg => $v) {

            if (0 !== \strpos($arg, $prefix))
                continue;

            $ret[\substr($arg, \strlen($prefix))] = $v;
        }
        return $ret;
    }

    public static function argShift(array &$args, string $key, $default = null)
    {
        if (\count($args) == 0)
            return $default;

        if (\array_key_exists($key, $args)) {
            $v = $args[$key];
            unset($args[$key]);
        } else {
            $keys = \array_values(\array_filter(\array_keys($args), 'is_int'));

            if (empty($keys))
                return $default;

            $v = $args[$keys[0]];
            unset($args[$keys[0]]);
        }
        return $v;
    }
}