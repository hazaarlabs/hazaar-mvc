<?php

declare(strict_types=1);

namespace Hazaar\Util\BTree;

class Node
{
    public int $ptr = 0;
    public int $length = 0;
    public int $slotSize = 8; // Size of each node slot in bytes

    /**
     * @var array<string,null|int>
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
        $node->slotSize = $slotSize;
        $node->children = [];

        return $node;
    }

    public function read(?int $ptr = null): bool
    {
        if (null !== $ptr) {
            $this->ptr = $ptr;
        }
        if (!$this->ptr) {
            return false;
        }
        fseek($this->file, $this->ptr);
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
        if (false === $data) {
            return false;
        }
        $this->children = [];
        for ($i = 0; $i < $this->length; $i += 20) {
            $len = strlen($data);
            if ($len < $i + 20) {
                break; // Prevent reading beyond the data length
            }
            $key = trim(substr($data, $i, 16)); // Extract key (16 bytes)
            $childPtr = unpack('L', substr($data, $i + 16, 4))[1]; // Extract pointer (4 bytes)
            if ($key && $childPtr > 0) {
                $this->children[$key] = $childPtr;
            }
        }

        return true;
    }

    public function write(?int $ptr = null): bool
    {
        if (null === $ptr) {
            $ptr = $this->ptr;
        }
        // Save the node to the file at the specified pointer
        fseek($this->file, $ptr);
        fwrite($this->file, pack('a', $this->nodeType->value));
        $data = '';
        foreach ($this->children as $key => $child) {
            $data .= pack('a16L', $key, $child);
        }
        $this->length = $this->slotSize * 20; // 16 byte key and 4 byte pointer
        fwrite($this->file, pack('L', $this->length));
        if (false === fwrite($this->file, str_pad($data, $this->length, "\0", STR_PAD_RIGHT), $this->length)) {
            return false;
        }
        $this->ptr = $ptr;

        return true;
    }

    public function set(string $key, mixed $value): bool
    {
        if (NodeType::LEAF !== $this->nodeType) {
            throw new \RuntimeException('Cannot set value in a non-leaf node.');
        }
        fseek($this->file, 0, SEEK_END);
        $ptr = ftell($this->file);
        $data = serialize($value);
        $dataLength = pack('L', strlen($data));
        fwrite($this->file, $dataLength);
        fwrite($this->file, $data);
        $this->children[$key] = $ptr;
        $this->write(ftell($this->file));

        return true;
    }

    public function get(string $key): mixed
    {
        if (NodeType::LEAF !== $this->nodeType) {
            throw new \RuntimeException('Cannot get value from a non-leaf node.');
        }
        if (!isset($this->children[$key])) {
            return null;
        }
        $ptr = $this->children[$key];
        fseek($this->file, $ptr);
        $lengthBuffer = fread($this->file, 4);
        if (false === $lengthBuffer || 4 !== strlen($lengthBuffer)) {
            return null;
        }
        $length = unpack('L', $lengthBuffer)[1];
        $data = fread($this->file, $length);
        if (false === $data || strlen($data) !== $length) {
            return null;
        }

        return unserialize($data);
    }

    public function remove(string $key): bool
    {
        if (NodeType::LEAF !== $this->nodeType) {
            throw new \RuntimeException('Cannot remove value from a non-leaf node.');
        }
        if (!isset($this->children[$key])) {
            return false; // Key does not exist
        }
        unset($this->children[$key]);
        $this->write();

        return true;
    }
}
