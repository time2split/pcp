<?php

declare(strict_types=1);

namespace Time2Split\PCP\Action;

interface IMoreActions
{
    public function isEmpty(): bool;
    public function getActions(): array;
}
