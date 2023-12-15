<?php
namespace Data;

trait IConfigMergeTrait
{

    public final function flatMerge(array $config): void
    {
        foreach ($config as $k => $v)
            $this[$k] = $v;
    }

    public final function merge(array|IConfig $config): void
    {
        if ($config instanceof IConfig) {
            $this->flatMerge($config->toArray());
            return;
        }
        \Help\Arrays::linearArrayRecursive($this, $config, $this->linearizePath(...));
    }

    private function linearizePath(array $path)
    {
        return \implode($this->getKeyDelimiter(), $path);
    }
}