<?php

declare(strict_types=1);

namespace Hazaar\Util\BTree;

class Node
{
    private const NODE_PTR_SIZE = 4; // Size of each pointer in bytes
    public int $ptr;
    public int $length;
    public int $slotSize; // Size of each node slot in bytes
    public int $keySize; // Maximum size of each key in bytes
    public int $childSize;
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
    final public function __construct(mixed $file, ?int $ptr = null, int $slotSize = 16, int $keySize = 32)
    {
        $this->file = $file;
        $this->parentNode = null;
        $this->nodeType = NodeType::INTERNAL; // Default to INTERNAL type
        $this->slotSize = $slotSize;
        $this->keySize = $keySize;
        $this->childSize = $this->keySize + self::NODE_PTR_SIZE; // Update child size based on new key size
        if (null !== $ptr) {
            $this->read($ptr);
        }
    }

    /**
     * Create a new Node instance with initialized children slots.
     *
     * @param resource $file The file resource where the BTree is stored
     */
    public static function create(
        mixed $file,
        NodeType $type = NodeType::INTERNAL,
        int $slotSize = 16,
        int $keySize = 32
    ): Node {
        $node = new static($file, slotSize: $slotSize, keySize: $keySize);
        $node->nodeType = $type;

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
        for ($i = 0; $i < $this->length; $i += $this->childSize) {
            $len = strlen($data);
            if ($len < $i + $this->childSize) {
                break; // Prevent reading beyond the data length
            }
            $key = trim(substr($data, $i, length: $this->keySize)); // Extract key (32 bytes)
            if ('' === $key) {
                break;
            }
            $this->children[$key] = unpack('L', substr($data, $i + $this->keySize, self::NODE_PTR_SIZE))[1];
        }
        $this->slotSize = $this->length / $this->childSize;
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
        foreach ($this->children as $key => $child) {
            $data .= pack('a'.$this->keySize.'L', $key, $child);
        }
        $this->length = $this->slotSize * $this->childSize;
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
        if (NodeType::LEAF !== $this->nodeType) {
            return $this->lookupChild($key)->set($key, $value);
        }
        $data = serialize($value);
        $dataLength = strlen($data);
        $curDataLength = 0;
        // If the key already exists, update its value
        if (isset($this->children[$key]) && $this->children[$key] > 0) {
            fseek($this->file, $this->children[$key]);
            $curDataLength = unpack('L', fread($this->file, self::NODE_PTR_SIZE))[1];
        }
        if ($dataLength > $curDataLength) {
            fseek($this->file, 0, SEEK_END);
            $this->children[$key] = ftell($this->file);
            ksort($this->children); // Ensure children are sorted by key
            fwrite($this->file, pack('L', $dataLength));
        }
        fwrite($this->file, $data);
        // If the node is full, split it.
        // NOTE:  This MUST be done after the set operation to ensure the value ends up in the correct node
        if (count($this->children) > $this->slotSize) {
            $this->split();
        } else {
            $this->write(); // Write the current node after setting the value
        }

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

    public function addNode(self $node): void
    {
        if (!$node->parentNode) {
            $node->parentNode = $this; // Set the parent node if not already set
        }
        if (NodeType::INTERNAL !== $this->nodeType) {
            throw new \RuntimeException('Cannot add node to a non-internal node.');
        }
        $newKey = array_key_last($node->children);
        if (isset($this->children[$newKey]) && $this->children[$newKey] !== $node->ptr) {
            throw new \RuntimeException('Node with the same key already exists in the parent node.');
        }
        if (!isset($node->ptr)) {
            $node->write(); // Ensure the new node is written before adding
        }
        $this->children[$newKey] = $node->ptr; // Add the new node's pointer to the current node
        ksort($this->children); // Ensure children are sorted by key
        if (count($this->children) > $this->slotSize) {
            $this->split();
        } else {
            $this->write(); // Write the current node after adding the new node
        }
    }

    public function verifyTree(?Node $node = null, ?string $minKey = null, ?string $maxKey = null): bool
    {
        if (null === $node) {
            $node = $this;
        }
        $keys = array_keys($node->children);
        $sortedKeys = $keys;
        sort($sortedKeys, SORT_STRING);
        if ($keys !== $sortedKeys) {
            return false; // Keys are not sorted
        }
        $childMinKey = $minKey;
        foreach ($node->children as $key => $ptr) {
            if (null !== $minKey && $key <= $minKey) {
                return false; // Key is not greater than minKey
            }
            if (null !== $maxKey && $key > $maxKey) {
                return false; // Key is not less than or equal to maxKey
            }
            if (NodeType::INTERNAL === $node->nodeType) {
                $childNode = self::$nodeCache[$ptr] ?? new self($node->file, $ptr);
                if (!$this->verifyTree($childNode, $childMinKey, $key)) {
                    return false;
                }
                $childMinKey = $key;
            }
        }

        return true;
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
            $node = new self($this->file, $childPtr);
            $node->parentNode = $this;

            return $node;
        }
        $newNode = self::create($this->file, NodeType::LEAF, $this->slotSize, $this->keySize);
        $newNode->children[$key] = 0; // Initialize the new node with the key
        $newNode->write(); // Create a new leaf node for the key
        $this->addNode($newNode);

        return $newNode; // Return the new node
    }

    private function split(): void
    {
        // Split the current node and promote the median key to the parent node
        $midIndex = (int) (count($this->children) / 2);
        $newNode = self::create(
            file: $this->file,
            type: $this->nodeType,
            slotSize: $this->slotSize,
            keySize: $this->keySize
        );
        $newNode->children = array_slice($this->children, 0, $midIndex, preserve_keys: true); // Move first half of children to newNode
        $newNode->write();
        $this->children = array_slice($this->children, $midIndex, preserve_keys: true); // Keep first half of children in current node
        if (null !== $this->parentNode) {
            // Promote the median key to the parent node
            $this->parentNode->addNode($newNode);
        } else {
            // This is the root node, create a new root
            $this->addNode($newNode);
        }

        // Write both nodes to the file
        $this->write();
    }

    private function cacheNode(self $node): void
    {
        if ($this->cacheSize <= 0) {
            return; // No caching if cache size is not set
        }
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
