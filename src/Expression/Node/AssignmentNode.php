<?php

namespace Time2Split\PCP\Expression\Node;

use Time2Split\Config\Configuration;

abstract class AssignmentNode extends BinaryNode
{

    protected function assign(Configuration $config, $offset, $val): mixed
    {
        return $config[$offset] = $this->dereferenceValue($config, $val);
    }

    protected function dereferenceValue(Configuration $config, $val): mixed
    {
        if ($val instanceof ConstNode)
            return $val->get($config);

        return $val;
    }
}
