<?php

declare(strict_types=1);

namespace Hazaar\Util\BTree;

class Node
{
    private const NODE_KEY_SIZE = 32; // Size of each key in bytes
    private const NODE_PTR_SIZE = 4; // Size of each pointer in bytes
    private const NODE_CHILD_SIZE = self::NODE_KEY_SIZE + self::NODE_PTR_SIZE; // Size of each child entry in bytes
    public int $ptr;
    public int $length = 0;
    public int $slotSize = 8; // Size of each node slot in bytes
    public int $cacheSize = 64;

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
     * Cache for nodes to avoid excessive file reads.
     *
     * @var array<int,self>
     */
    private static array $nodeCache = [];
    private ?self $parentNode;

    /**
     * Node constructor.
     *
     * @param resource $file The file resource where the BTree is stored
     * @param int      $ptr  The pointer to the node in the file
     */
    final public function __construct(mixed $file, ?int $ptr = null, ?self $parentNode = null)
    {
        $this->file = $file;
        $this->parentNode = $parentNode;
        $this->nodeType = NodeType::INTERNAL; // Default to INTERNAL type
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
    public static function create(mixed $file, int $slotSize, NodeType $type = NodeType::INTERNAL, ?self $parentNode = null): Node
    {
        $node = new static($file);
        $node->nodeType = $type;
        if (null === $parentNode) {
            self::$nodeCache[] = [];
        } else {
            $node->parentNode = $parentNode;
        }
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
        $lengthBuffer = fread($this->file, self::NODE_PTR_SIZE);
        if (false === $lengthBuffer || self::NODE_PTR_SIZE !== strlen($lengthBuffer)) {
            return false;
        }
        $this->length = unpack('L', $lengthBuffer)[1];
        $data = fread($this->file, $this->length);
        if (false === $data) {
            return false;
        }
        $this->children = [];
        for ($i = 0; $i < $this->length; $i += self::NODE_CHILD_SIZE) {
            $len = strlen($data);
            if ($len < $i + self::NODE_CHILD_SIZE) {
                break; // Prevent reading beyond the data length
            }
            $key = trim(substr($data, $i, length: self::NODE_KEY_SIZE)); // Extract key (32 bytes)
            $childPtr = unpack('L', substr($data, $i + self::NODE_KEY_SIZE, self::NODE_PTR_SIZE))[1];
            if ($key && $childPtr > 0) {
                $this->children[$key] = $childPtr;
            }
        }
        $this->slotSize = $this->length / self::NODE_CHILD_SIZE;
        $this->cacheNode($this);

        return true;
    }

    public function write(?int $ptr = null): bool
    {
        if (null === $ptr) {
            if (!isset($this->ptr)) {
                fseek($this->file, 0, SEEK_END);
                $this->ptr = ftell($this->file);
            }
            $ptr = $this->ptr;
        }
        // Save the node to the file at the specified pointer
        fseek($this->file, $ptr);
        fwrite($this->file, pack('a', $this->nodeType->value));
        $data = '';
        ksort($this->children); // Ensure children are sorted by key
        foreach ($this->children as $key => $child) {
            $data .= pack('a'.self::NODE_KEY_SIZE.'L', $key, $child);
        }
        $this->length = $this->slotSize * self::NODE_CHILD_SIZE;
        fwrite($this->file, pack('L', $this->length));
        if (false === fwrite($this->file, str_pad($data, $this->length, "\0", STR_PAD_RIGHT), $this->length)) {
            return false;
        }
        $this->ptr = $ptr;
        $this->cacheNode($this); // Cache the node after writing

        return true;
    }

    public function set(string $key, mixed $value): bool
    {
        if (count($this->children) >= $this->slotSize && !isset($this->children[$key])) {
            $this->split();
        }
        if (NodeType::LEAF !== $this->nodeType) {
            return $this->lookupChild($key)->set($key, $value);
        }
        $data = serialize($value);
        $dataLength = strlen($data);
        $curDataLength = 0;
        // If the key already exists, update its value
        if (isset($this->children[$key])) {
            fseek($this->file, $this->children[$key]);
            $curDataLength = unpack('L', fread($this->file, self::NODE_PTR_SIZE))[1];
        }
        if ($dataLength > $curDataLength) {
            fseek($this->file, 0, SEEK_END);
            $this->children[$key] = ftell($this->file);
            fwrite($this->file, pack('L', $dataLength));
        }
        fwrite($this->file, $data);
        $this->write();

        return true;
    }

    public function get(string $key): mixed
    {
        if (NodeType::INTERNAL === $this->nodeType) {
            return $this->lookupChild($key)->get($key);
        }
        if (!isset($this->children[$key])) {
            return null;
        }

        return $this->readValue($this->children[$key]);
    }

    public function remove(string $key): bool
    {
        if (NodeType::LEAF !== $this->nodeType) {
            return $this->lookupChild($key)->remove($key);
        }
        if (!isset($this->children[$key])) {
            return false; // Key does not exist
        }
        unset($this->children[$key]);
        $this->write();

        return true;
    }

    /**
     * Return the entire B-Tree node and its children as an array.
     *
     * @param array<string,mixed> $result The array to populate with node data
     */
    public function toArray(array &$result): void
    {
        if (NodeType::LEAF === $this->nodeType) {
            foreach ($this->children as $key => $ptr) {
                $result[$key] = $this->readValue($ptr);
            }

            return;
        }
        foreach ($this->children as $key => $ptr) {
            $node = self::$nodeCache[$ptr] ?? new self($this->file, $ptr);
            $node->toArray($result);
            $this->cacheNode($node); // Cache the node after reading
        }
    }

    private function readValue(int $ptr): mixed
    {
        fseek($this->file, $ptr);
        $lengthBuffer = fread($this->file, self::NODE_PTR_SIZE);
        if (false === $lengthBuffer || self::NODE_PTR_SIZE !== strlen($lengthBuffer)) {
            return null;
        }
        $length = unpack('L', $lengthBuffer)[1];
        $data = fread($this->file, $length);
        if (false === $data || strlen($data) !== $length) {
            return null;
        }

        return unserialize($data);
    }

    private function lookupChild(string $key): Node
    {
        if (NodeType::LEAF === $this->nodeType) {
            throw new \RuntimeException('Cannot lookup child in a leaf node.');
        }
        foreach ($this->children as $childKey => $childPtr) {
            if (!($key <= $childKey)) {
                continue;
            }
            if (isset(self::$nodeCache[$childPtr])) {
                return self::$nodeCache[$childPtr]; // Return cached node
            }

            // Return the child node that should contain the key
            return new self($this->file, $childPtr, $this);
        }
        if (count($this->children) >= $this->slotSize) {
            // If the node is full, create a new leaf node
            $this->split();
        }
        $newNode = self::create($this->file, $this->slotSize, NodeType::LEAF, $this);
        $newNode->write(); // Create a new leaf node for the key
        $this->children[$key] = $newNode->ptr;
        $this->write();

        return $newNode; // Return the new node
    }

    private function split(): void
    {
        if (NodeType::LEAF === $this->nodeType) {
            $this->nodeType = NodeType::INTERNAL; // Change the type to INTERNAL
            $newNode1 = self::create($this->file, $this->slotSize, NodeType::LEAF, $this);
            $newNode2 = self::create($this->file, $this->slotSize, NodeType::LEAF, $this);
            $keys = array_keys($this->children);
            $midIndex = (int) (count($keys) / 2);
            $midKey = $keys[$midIndex - 1];
            $lastKey = end($keys);
            foreach ($this->children as $key => $value) {
                if ($key <= $midKey) {
                    $newNode1->children[$key] = $this->children[$key];
                } else {
                    $newNode2->children[$key] = $this->children[$key];
                }
                unset($this->children[$key]);
            }
            $newNode1->write();
            $newNode2->write();
            // Set the new nodes as children of the current node
            $this->children = [
                $midKey => $newNode1->ptr,
                $lastKey => $newNode2->ptr,
            ];
        } else {
            // Split the current internal node
            $newNode = self::create($this->file, $this->slotSize, $this->nodeType, $this->parentNode ?? $this);
            $keys = array_keys($this->children);
            $midIndex = (int) (count($keys) / 2);
            $lastKey = end($keys);

            // Move the second half of children to the new node
            for ($i = $midIndex; $i < count($keys); ++$i) {
                $key = $keys[$i];
                $newNode->children[$key] = $this->children[$key];
                unset($this->children[$key]);
            }
            $newNode->write();

            if (null !== $this->parentNode) {
                // Promote the median key to the parent node
                $this->parentNode->children[$lastKey] = $newNode->ptr;
                $this->parentNode->write();
            } else {
                // This is the root node, create a new root
                $this->children[$lastKey] = $newNode->ptr;
                $this->write();
            }
        }

        // Write both nodes to the file
        $this->write();
    }

    private function cacheNode(self $node): void
    {
        if (!isset($node->ptr) || $node->ptr <= 0) {
            throw new \RuntimeException('Invalid node pointer, cannot cache node.');
        }
        if (array_key_exists($node->ptr, self::$nodeCache)) {
            return; // Node is already cached
        }
        while (count(self::$nodeCache) >= $node->cacheSize) {
            array_shift(self::$nodeCache); // Remove the oldest cached node if cache size exceeds limit
        }
        self::$nodeCache[$node->ptr] = $node; // Cache the node for future access
    }
}
