<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\PCP\App;

final class MoreActions
{
    private function __construct(
        protected readonly array $actions,
        protected readonly Configuration $config,
    ) {
        self::checkTypeOfActions(...$actions);
    }

    private static function checkTypeOfActions(ActionCommand ...$commands): void {}

    // ========================================================================

    public static function create(array $actions, Configuration $config = null): MoreActions
    {
        if (empty($actions))
            return self::empty();

        return new self($actions, $config ?? App::emptyConfiguration());
    }

    private static self $emptyInstance;

    public static function empty(): MoreActions
    {
        if (isset(self::$emptyInstance))
            return self::$emptyInstance;

        return self::$emptyInstance =  new self([], App::emptyConfiguration());
    }

    // ========================================================================

    public function getActions(): array
    {
        if (0 == $this->config->count())
            return $this->actions;

        $actions = [];
        $config = $this->config;

        foreach ($this->actions as $action) {
            $arguments = (clone $action->getArguments())
                ->merge($config->getRawValueIterator());

            // $actions[] = $action->copy($actionConfig);
            $actions[] = ActionCommand::create($action->getName(), $arguments);
        }
        return $actions;
    }

    public function isEmpty(): bool
    {
        return 0 === \count($this->actions);
    }
}
