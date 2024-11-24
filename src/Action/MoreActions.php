<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

use Time2Split\Config\Configuration;
use Time2Split\PCP\App;

final class MoreActions extends AbstractMoreActions
{
    public static function create(array $actions, Configuration $config = null): IMoreActions
    {
        if (empty($actions))
            return self::empty();

        return new self($actions, $config ?? App::emptyConfiguration());
    }

    private static self $emptyInstance;

    public static function empty(): IMoreActions
    {
        if (isset(self::$emptyInstance))
            return self::$emptyInstance;

        return self::$emptyInstance =  new self([], App::emptyConfiguration());
    }

    // ========================================================================

    public function isEmpty(): bool
    {
        return $this === self::empty();
    }
}
