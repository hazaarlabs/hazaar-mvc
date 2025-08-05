<?php

declare(strict_types=1);

namespace Hazaar\Util\BTree;

enum NodeType: string
{
    case INTERNAL = 'i'; // Internal node
    case LEAF = 'l'; // Leaf node
}
