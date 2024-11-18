<?php

declare(strict_types=1);

namespace Time2Split\PCP\Expression\Node;

use Time2Split\Config\Configuration;

abstract class AssignmentNode extends BinaryNode
{

    protected function dereferenceValue(Configuration $config, Node $val): mixed
    {
        if ($val instanceof ConstNode)
            return $val->getValue();

        return $val;
    }
}
