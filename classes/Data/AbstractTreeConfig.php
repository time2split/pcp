<?php
namespace Data;

abstract class AbstractTreeConfig implements IConfig
{

    protected string $delimiter;

    protected function __construct(string $delimiter)
    {
        $this->delimiter = $delimiter;
    }

    public function getKeyDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Merge the first level of an array, that is replace all value in the TreeConfig by those of the array.
     *
     * @param array $config
     */
    public final function mergeArray(array $config): void
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
    public final function mergeArrayRecursive(array $config): void
    {
        \Help\Arrays::linearArrayRecursive($this, $config, $this->linearizePath(...));
    }

    private function linearizePath(array $path)
    {
        return \implode($this->delimiter, $path);
    }
}