<?php

declare(strict_types=1);

namespace Hazaar\Util\BTree;

class Node
{
    public int $ptr = 0;
    public int $length = 0;

    /**
     * @var array<int,?Node>
     */
    public array $children = [];
    public NodeType $nodeType;

    /**
     * @var resource
     */
    protected mixed $file;

    /**
     * Node constructor.
     *
     * @param resource $file The file resource where the BTree is stored
     * @param int      $ptr  The pointer to the node in the file
     */
    final public function __construct(mixed $file, ?int $ptr = null)
    {
        $this->file = $file;
        if (null !== $ptr) {
            $this->read($ptr);
        }
    }

    /**
     * Create a new Node instance with initialized children slots.
     *
     * @param resource $file     The file resource where the BTree is stored
     * @param int      $slotSize The number of slots in the node
     */
    public static function create(mixed $file, int $slotSize, NodeType $type = NodeType::INTERNAL): Node
    {
        $node = new static($file);
        $node->nodeType = $type;
        $node->children = array_fill(0, $slotSize, null); // Initialize children slots

        return $node;
    }

    public function read(int $ptr): bool
    {
        fseek($this->file, $ptr);
        $typeBuffer = fread($this->file, 1);
        if (false === $typeBuffer || 1 !== strlen($typeBuffer)) {
            return false;
        }
        $this->nodeType = NodeType::from(unpack('a', $typeBuffer)[1]);
        $lengthBuffer = fread($this->file, 4);
        if (false === $lengthBuffer || 4 !== strlen($lengthBuffer)) {
            return false;
        }
        $this->length = unpack('L', $lengthBuffer)[1];
        $data = fread($this->file, $this->length);
        if (false === $data || strlen($data) !== $this->length) {
            return false;
        }

        return true;
    }

    public function write(int $ptr): bool
    {
        // Save the node to the file at the specified pointer
        fseek($this->file, $ptr);
        fwrite($this->file, pack('a', $this->nodeType->value));
        $data = serialize($this->children);
        $this->length = strlen($data);
        fwrite($this->file, pack('L', $this->length));
        if (false === fwrite($this->file, $data)) {
            return false;
        }
        $this->ptr = $ptr;

        return true;
    }
}
