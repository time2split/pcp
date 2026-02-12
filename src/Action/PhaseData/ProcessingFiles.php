<?php

namespace Time2Split\PCP\Action\PhaseData;

use Time2Split\PCP\FS\FSDir;

final class ProcessingFiles
{
    public function __construct(
        public readonly FSDir $fsSrcRoot,
        public readonly FSDir $fsWDRoot,
    ) {}
}
