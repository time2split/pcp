<?php
namespace Data;

/**
 * A TreeConfig is a hierarchical configuration where its element can be accessed in a single key representing a path in the tree;
 * each section of the path is delimited by an internal char delimiter.
 * Each node of the configuration can be set to a value.
 *
 *
 * @author zuri
 */
final class TreeConfig implements IConfig, \Iterator
{

    private const default = \Help\NullValue::v;

    private mixed $nullValue;

    private array $data;

    private string $delimiter;

    // ========================================================================
    private function __construct(string $delimiter, $default = self::default)
    {
        $this->delimiter = $delimiter;
        $this->data = [];
        $this->nullValue = $default;
    }

    public static function empty(string $delimiter = '.'): self
    {
        return new self($delimiter);
    }

    public function child(): static
    {
        return new self($this->delimiter, $this->nullValue);
    }

    // ========================================================================
    public function setNullValue($default)
    {
        $this->nullValue = $default;
    }

    public function getNullValue(): mixed
    {
        return $this->nullValue;
    }

    public function get($offset): mixed
    {
        $val = $this->getData($offset, $this->nullValue);

        if (\is_array($val))
            $val = $val[''] ?? $this->nullValue;

        return $val;
    }

    public function &getReference($offset): mixed
    {
        return $this->createIfNotPresent($offset, $this->nullValue);
    }

    public function subConfig(mixed $offset): static
    {
        $val = $this->getData($offset, $this->nullValue);
        $ret = $this->child();

        if ($val !== $this->nullValue)
            $ret->data = $val;

        return $ret;
    }

    public function unset($offset): bool
    {
        $path = $this->explodePath($offset);
        $last = \array_pop($path);
        $val = &\Help\Arrays::follow($this->data, $path, $this->nullValue);

        if ($val === $this->nullValue)
            return false;

        unset($val[$last]);
        return true;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function toArray(): array
    {
        return $this->toNaturalArray($this->data);
    }

    // ========================================================================
    private function explodePath(string $key): array
    {
        return \explode($this->delimiter, $key);
    }

    private function &getData($offset): mixed
    {
        $val = &\Help\Arrays::follow($this->data, $this->explodePath($offset), $this->nullValue);

        if ($val === $this->nullValue) {

            if (isset($this->parent))
                $val = &$this->parent->getData($offset);
            else
                $val = $this->nullValue;
        }
        return $val;
    }

    private function getUpdateList($offset, $value): array
    {
        $path = $this->explodePath("$offset$this->delimiter");
        return \Help\Arrays::pathToRecursiveList($path, $value);
    }

    private static function updateOnUnexists(&$data, $k, $v): void
    {
        $data[$k] = $v;
    }

    private function &createIfNotPresent($offset): mixed
    {
        $ref = [];
        $update = $this->getUpdateList($offset, null);

        \Help\Arrays::updateRecursive($update, $this->data, //
        self::updateOnUnexists(...), //
        null, //
        function (&$aref) use (&$ref) {
            $ref[] = &$aref;
        });
        return $ref[0];
    }

    private function toNaturalArray($data): array
    {
        if (empty($data))
            return [];

        $ret = [];

        foreach ($data as $k => $v) {

            if (\is_array($v)) {

                if (isset($v[''])) {
                    $ret[$k] = $v[''];
                    unset($v['']); // Does not count for the next foreach
                }

                foreach (self::toNaturalArray($v) as $kk => $vv)
                    $ret["$k$this->delimiter$kk"] = $vv;
            }
        }
        return $ret;
    }

    // ========================================================================
    public function offsetExists($offset): bool
    {
        return $this->nullValue !== \Help\Arrays::follow($this->data, $this->explodePath($offset), $this->nullValue);
    }

    public function &offsetGet($offset): mixed
    {
        // Ask for the reference at this level
        if (\str_ends_with($offset, "&"))
            $val = &$this->getReference(\substr($offset, 0, - 1));
        elseif (\str_ends_with($offset, "[]")) {
            $val = $this->getData(\substr($offset, 0, - 2));
            $val = $this->toNaturalArray($val);
        } else {
            $val = $this->get($offset);
        }
        return $val;
    }

    public function offsetSet($offset, $value): void
    {
        $update = $this->getUpdateList($offset, $value);
        \Help\Arrays::updateRecursive($update, $this->data, self::updateOnUnexists(...));
    }

    public function offsetUnset($offset): void
    {
        $this->unset($offset);
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

    public function current(): mixed
    {
        return $this[$this->itk];
    }

    public function key(): mixed
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
     * Merge the first level of an array, that is replace only the non existant path in the TreeConfig by those in the array.
     *
     * @param array $config
     */
    public function union(iterable $config): void
    {
        foreach ($config as $k => $v)

            if (! isset($this[$k]))
                $this[$k] = $v;
    }

    // ========================================================================

    /**
     * Merge the first level of an array, that is replace all value in the TreeConfig by those of the array.
     *
     * @param array $config
     */
    public function mergeArray(array $config): void
    {
        foreach ($config as $k => $v)
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
    public function mergeArrayRecursive(array $config): void
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