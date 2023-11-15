<?php
namespace Data;

/**
 * A TreeConfig is a hierarchical configuration where its element can be accessed in a single key representing a path in the tree;
 * each section of the path is delimited by an internal char delimiter.
 * Each node of the configuration can be set to a value.
 *
 * @author zuri
 */
final class TreeConfig implements \ArrayAccess
{

    private static $default;

    private static $null = null;

    private array $data = [];

    private string $delimiter;

    private ?TreeConfig $parent;

    public function __construct(TreeConfig $parent = null, string $delimiter = '.')
    {
        if (self::$default === null)
            self::$default = new \StdClass();

        $this->parent = $parent;
        $this->delimiter = $delimiter;
    }

    /**
     * Transform a configuration array to a TreeConfig.
     * The transformation occurs recursively with sub-array.
     * If a sub array is a list, then the list is considered as a simple value and the recursion stop.
     *
     * @param array $config
     *            The configuration data
     * @return TreeConfig
     */
    public static function fromArray(array $config, TreeConfig $parent = null, string $delimiter = '.'): TreeConfig
    {
        $ret = new TreeConfig($parent, $delimiter);

        \Help\Arrays::walk_branches($config, function ($path, $val) use ($ret) {
            $ret[\implode($ret->delimiter, $path)] = $val;
        }, function ($path, $val) use ($ret) {
            if (\is_array_list($val)) {
                $ret[\implode($ret->delimiter, $path)] = $val;
                return false;
            }
            return true;
        });
        return $ret;
    }

    private function explodePath(string $key): array
    {
        return \explode($this->delimiter, $key);
    }

    private function &getData($offset)
    {
        $val = &\Help\Arrays::follow($this->data, $this->explodePath($offset), self::$default);

        if ($val === self::$default && null !== $this->parent)
            $val = &$this->getData($offset);

        if ($val === self::$default)
            return self::$null;

        return $val;
    }

    public function subTreeConfig($offset): TreeConfig
    {
        $val = $this->getData($offset);
        $ret = new TreeConfig(null, $this->delimiter);

        // if ($val === self::$default)
        // return $ret;

        $ret->data = $val;
        return $ret;
    }

    public function offsetExists($offset): bool
    {
        return self::$default !== \Help\Arrays::follow($this->data, $this->explodePath($offset)) || //
        (null !== $this->parent && $this->parent->offsetExists($offset));
    }

    public function &offsetGet($offset)
    {
        $val = $this->getData($offset);

        if ($val === self::$default)
            return self::$null;
        if (\is_array($val))
            $val = &$val[''] ?? self::$null;

        return $val;
    }

    public function offsetSet($offset, $value): void
    {
        $path = $this->explodePath("$offset$this->delimiter");
        $update = \Help\Arrays::pathToRecursiveList($path, $value);
        \Help\Arrays::updateRecursive($update, $this->data, function (&$data, $k, $v) {
            if (! \is_array($v))
                $data[$k] = $v;
            else
                $data[$k] = [];
        });
    }

    public function offsetUnset($offset): void
    {
        $path = $this->explodePath($offset);
        $last = \array_pop($path);
        $val = &\Help\Arrays::follow($this->data, $path, self::$default);

        if ($val === self::$default)
            return;

        unset($val[$last]);
    }

    public function keys(): array
    {
        $ret = [];
        $delim = $this->delimiter;

        \Help\Arrays::walk_branches($this->data, function ($path, $val) use (&$ret, $delim) {
            $ret[] = \rtrim(\implode($delim, $path), '.');
        }, function ($path, $val) use (&$ret, $delim) {
            if (\is_array_list($val)) {
                $ret[] = \rtrim(\implode($delim, $path), '.');
                return false;
            }
            return true;
        });
        return $ret;
    }

    public function merge(TreeConfig $config): void
    {
        foreach ($config->keys() as $k)
            $this[$k] = $config[$k];
    }

    /**
     * Merge the first level of an array, that is replace all value in the TreeConfig by those of the array.
     *
     * @param array $config
     */
    public function arrayMerge(array $config): void
    {
        $update = [];

        foreach ($config as $k => $v)
            $this[$k] = $v;
    }

    /**
     * Merge the first level of an array, that is replace only the non existant path in the TreeConfig by those in the array.
     *
     * @param array $config
     */
    public function arrayUnion(array $config): void
    {
        $update = [];

        foreach ($config as $k => $v)
            if (! isset($this[$k]))
                $this[$k] = $v;
    }
}