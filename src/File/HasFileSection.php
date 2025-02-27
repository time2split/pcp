<?php

declare(strict_types=1);

namespace Time2Split\PCP\File;

interface HasFileSection
{
    public function getFileSection(): Section;
}
