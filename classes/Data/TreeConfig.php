<?php
namespace Data;

/**
 * A TreeConfig is a hierarchical configuration where its element can be accessed in a single key representing a path in the tree;
 * each section of the path is delimited by an internal char delimiter.
 * Each node of the configuration can be set to a value.
 *
 * A TreeConfig may have a parent which is immutable in which elements can be searched if not present the child.
 *
 * @author zuri
 */
final class TreeConfig implements \ArrayAccess, \Iterator
{

    private array $data = [];

    private string $delimiter;

    private ?TreeConfig $parent;

    // ========================================================================
    private function __construct(?TreeConfig $parent, string $delimiter)
    {
        $this->parent = $parent;
        $this->delimiter = $delimiter;
    }

    public static function empty(string $delimiter = '.'): self
    {
        return new self(null, $delimiter);
    }

    public static function fromParent(TreeConfig $parent): self
    {
        return new self($parent, $parent->delimiter);
    }

    // ========================================================================
    public function clearLevel()
    {
        $this->data = [];
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function child(): self
    {
        return self::fromParent($this);
    }

    public function toArray(): array
    {
        return \array_merge((array)$this->parent?->toArray(),$this->toNaturalArray($this->data));
    }

    // ========================================================================
    private function explodePath(string $key): array
    {
        return \explode($this->delimiter, $key);
    }

    private function &getData($offset)
    {
        $val = &\Help\Arrays::follow($this->data, $this->explodePath($offset), \Help\NullValue::v);

        if ($val === \Help\NullValue::v) {

            if (isset($this->parent))
                $val = &$this->parent->getData($offset);
            else
                $val = \Help\NullValue::v;
        }
        return $val;
    }

    private function getUpdateList($offset, $value)
    {
        $path = $this->explodePath("$offset$this->delimiter");
        return \Help\Arrays::pathToRecursiveList($path, $value);
    }

    private static function updateOnUnexists(&$data, $k, $v)
    {
        $data[$k] = $v;
    }

    private function &createIfNotPresent($offset)
    {
        $ref = [];
        $update = $this->getUpdateList($offset, null);

        \Help\Arrays::updateRecursive($update, $this->data, //
        function ($a, $k, $v) {
            self::updateOnUnexists($a, $k, $v);
        }, //
        null, //
        function (&$aref) use (&$ref) {
            $ref[] = &$aref;
        });
        return $ref[0];
    }

    public function subTreeConfig($offset): TreeConfig
    {
        $val = $this->getData($offset);
        $ret = new TreeConfig(null, $this->delimiter);

        if ($val !== \Help\NullValue::v)
            $ret->data = $val;

        return $ret;
    }

    // ========================================================================
    public function offsetExists($offset): bool
    {
        return null !== \Help\Arrays::follow($this->data, $this->explodePath($offset)) || //
        (null !== $this->parent && $this->parent->offsetExists($offset));
    }

    private function toNaturalArray($data): array
    {
        $ret = [];

        foreach ($data as $k => $v) {

            if (\is_array($v) && isset($v[''])) {
                $ret[$k] = $v[''] ?? null;
                unset($v['']);
            } else
                $ret[$k] = null;

            if (\is_array($v) && ! empty($v)) {

                foreach (self::toNaturalArray($v) as $kk => $vv)
                    $ret["$k$this->delimiter$kk"] = $vv;
            }
        }
        return $ret;
    }

    public function &offsetGet($offset)
    {
        // Ask for the reference at this level
        if (\str_ends_with($offset, "&"))
            $val = &$this->createIfNotPresent(\substr($offset, 0, - 1));
        elseif (\str_ends_with($offset, "[]")) {
            $val = $this->getData(\substr($offset, 0, - 2));
            $val = $this->toNaturalArray($val);
        } else {
            $val = $this->getData($offset);

            if ($val === \Help\NullValue::v)
                $val = null;
            if (\is_array($val))
                $val = $val[''] ?? null;
        }
        return $val;
    }

    public function offsetSet($offset, $value): void
    {
        $update = $this->getUpdateList($offset, $value);
        $ref = [];

        \Help\Arrays::updateRecursive($update, $this->data, function ($a, $k, $v) {
            self::updateOnUnexists($a, $k, $v);
        });
    }

    public function offsetUnset($offset): void
    {
        $path = $this->explodePath($offset);
        $last = \array_pop($path);
        $val = &\Help\Arrays::follow($this->data, $path, \Help\NullValue::v);

        if ($val === \Help\NullValue::v)
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

    // ========================================================================
    private array $itKeys;

    private $itk;

    public function current()
    {
        return $this[$this->itk];
    }

    public function key()
    {
        return $this->itk;
    }

    public function next(): void
    {
        $this->itk = \array_pop($this->itKeys);
    }

    public function rewind(): void
    {
        $this->itKeys = \array_reverse($this->keys());
        $this->itk = \array_pop($this->itKeys);
    }

    public function valid(): bool
    {
        return isset($this->itk);
    }

    // ========================================================================
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
        foreach ($config as $k => $v)

            if (! isset($this[$k]))
                $this[$k] = $v;
    }

    /**
     * Merge a configuration array into a TreeConfig.
     * The transformation occurs recursively with sub-array.
     * If a sub array is a list, then the list is considered as a simple value and the recursion stop.
     *
     * @param array $config
     *            The configuration data
     * @return TreeConfig
     */
    public function arrayMergeRecursive(array $config): void
    {
        $ret = $this;

        \Help\Arrays::walk_branches($config, function ($path, $val) use ($ret) {
            $ret[\implode($ret->delimiter, $path)] = $val;
        }, function ($path, $val) use ($ret) {
            if (\is_array_list($val)) {
                $ret[\implode($ret->delimiter, $path)] = $val;
                return false;
            }
            return true;
        });
    }
}