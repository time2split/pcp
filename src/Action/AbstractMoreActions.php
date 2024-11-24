<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;

abstract class AbstractMoreActions implements IMoreActions
{
    protected function __construct(
        protected array $actions,
        protected Configuration $config,
    ) {}

    public function getActions(): array
    {
        if (0 == $this->config->count())
            return $this->actions;

        $actions = [];
        $config = $this->config;

        foreach ($this->actions as $action) {
            $actionConfig = (clone $action->getArguments())
                ->merge($config->getRawValueIterator());
            $actions[] = $action->copy($actionConfig);
        }
        return $actions;
    }

    public function isEmpty(): bool
    {
        return 0 === \count($this->actions);
    }
}
