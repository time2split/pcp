<?php

declare(strict_types=1);

namespace Time2Split\PCP\C\Element;

use Time2Split\PCP\File\Section;

interface CPPDirective extends CElement
{
    public function getFileSection(): Section;

    public function getText(): string;

    public function getDirective(): string;
}
