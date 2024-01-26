<?php
namespace Time2Split\PCP\Expression\Node;

use Time2Split\Config\Configuration;

abstract class AssignmentNode extends BinaryNode
{

    protected function assign(Configuration $config, $offset, $val): mixed
    {
        if ($val instanceof ConstNode)
            $val = $val->get($config);

        return $config[$offset] = $val;
    }
}