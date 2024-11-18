<?php

namespace Time2Split\PCP\Action;

interface ActionFactory
{
    public function getActions(string $action): array;
}
