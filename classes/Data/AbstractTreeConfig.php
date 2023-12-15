<?php
namespace Data;

abstract class AbstractTreeConfig implements IConfig
{
    use IConfigMergeTrait;

    protected string $delimiter;

    protected function __construct(string $delimiter)
    {
        $this->delimiter = $delimiter;
    }

    public function getKeyDelimiter(): string
    {
        return $this->delimiter;
    }
}