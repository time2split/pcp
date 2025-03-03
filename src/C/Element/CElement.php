<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\Element;

use Time2Split\Help\Set;

interface CElement
{
    public function getElementType(): Set;
}
