<?php

declare(strict_types=1);

namespace Hazaar\Util\BTree;

class Node
{
    public const NODE_PTR_SIZE = 4; // Size of each pointer in bytes
    public int $ptr;
    public int $length;
    public int $slotSize; // Size of each node slot in bytes
    public int $keySize; // Maximum size of each key in bytes
    public int $childSize;
    public int $cacheSize = 128;

    /**
     * @var array<string,null|int>
     */
    public array $children = [];
    public NodeType $nodeType;

    /**
     * @var resource
     */
    public mixed $file;

    /**
     * Cache for nodes to avoid excessive file reads.
     *
     * @var array<int,self>
     */
    private static array $nodeCache = [];

    /**
     * Cache for records to avoid multiple reads from the file.
     * This is a static property to allow sharing across instances.
     *
     * @var array<int,Record>
     */
    private static array $recordCache = [];
    private ?self $parentNode;

    /**
     * Node constructor.
     *
     * @param resource $file The file resource where the BTree is stored
     * @param int      $ptr  The pointer to the node in the file
     */
    final public function __construct(mixed $file, ?int $ptr = null, int $slotSize = 16, int $keySize = 32)
    {
        if ($slotSize < 4) {
            throw new \InvalidArgumentException('Slot size must be at least 4.');
        }
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

    /**
     * Reads the node data from the file at the specified pointer.
     *
     * @param null|int $ptr The pointer to the node in the file. If null, uses the current node's pointer.
     *
     * @return bool returns true on success, false on failure
     */
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

    /**
     * Writes the node data to the file at the specified pointer.
     *
     * @param null|int $ptr The pointer to write the node in the file. If null, writes to the end of the file.
     *
     * @return bool returns true on success, false on failure
     */
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

    /**
     * Sets the value for the specified key in the node.
     *
     * @param string $key   the key to set
     * @param mixed  $value the value to associate with the key
     *
     * @return bool returns true on success
     */
    public function set(string $key, mixed $value): bool
    {
        if (NodeType::LEAF !== $this->nodeType) {
            $childNode = $this->lookupChild($key);
            if ($childNode) {
                return $childNode->set($key, $value);
            }
            $childNode = self::create(
                $this->file,
                NodeType::LEAF,
                $this->slotSize,
                $this->keySize
            );
            $childNode->set($key, $value);
            $this->addNode($childNode);

            return true;
        }
        $record = self::$recordCache[$key] ?? Record::create($this);
        if (!$record->write($key, $value)) {
            return false; // If the record write fails, return false
        }
        $this->addRecord($record);
        // If the node is full, split it.
        // NOTE:  This MUST be done after the set operation to ensure the value ends up in the correct node
        if (count($this->children) > $this->slotSize) {
            $this->split();
        } else {
            $this->write(); // Write the current node after setting the value
        }

        return true;
    }

    /**
     * Gets the value for the specified key from the node.
     *
     * @param string $key the key to get
     *
     * @return mixed the value associated with the key, or null if the key does not exist
     */
    public function get(string $key): mixed
    {
        if (NodeType::INTERNAL === $this->nodeType
            && $childNode = $this->lookupChild($key)) {
            return $childNode->get($key);
        }
        if (!isset($this->children[$key])) {
            return null;
        }
        $record = self::$recordCache[$key] ?? Record::create($this);

        return $record->read($this->children[$key]);
    }

    /**
     * Removes the specified key from the node.
     *
     * @param string $key the key to remove
     *
     * @return bool returns true on success, false if the key does not exist
     */
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
                if (array_key_exists($key, $result)) {
                    throw new \RuntimeException("Duplicate key found: {$key}");
                }
                $record = self::$recordCache ?? Record::create($this);
                $result[$key] = $record->read($ptr);
            }

            return;
        }
        foreach ($this->children as $key => $ptr) {
            $node = self::$nodeCache[$ptr] ?? new self($this->file, $ptr);
            $node->toArray($result);
            $this->cacheNode($node); // Cache the node after reading
        }
    }

    /**
     * Adds a child node to the current node.
     *
     * @param self $node the node to add as a child
     *
     * @throws \RuntimeException if the node already has a parent, if the current node is not an internal node, or if a node with the same key already exists
     */
    public function addNode(self $node): void
    {
        if ($node->parentNode) {
            throw new \RuntimeException('Node already has a parent, cannot add to another node.');
        }
        $node->parentNode = $this; // Set the parent node if not already set
        if (NodeType::INTERNAL !== $this->nodeType) {
            throw new \RuntimeException('Cannot add node to a non-internal node.');
        }
        $newKey = array_key_last($node->children);
        if (isset($this->children[$newKey], $node->ptr) && $this->children[$newKey] !== $node->ptr) {
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

    /**
     * Adds a record to the current node.
     *
     * @param Record $record the record to add
     *
     * @return bool returns true on success, false if the node is not a leaf
     */
    public function addRecord(Record $record): bool
    {
        if (!(NodeType::LEAF === $this->nodeType
            && isset($record->ptr)
            && $record->ptr > 0
            && isset($record->key))) {
            return false;
        }
        $this->children[$record->key] = $record->ptr; // Add the record's pointer to the current node
        ksort($this->children); // Ensure children are sorted by key
        while (count(self::$recordCache) >= $this->cacheSize) {
            array_shift(self::$recordCache); // Remove the oldest cached record if cache size exceeds limit
        }
        self::$recordCache[$record->key] = $record; // Cache the record for future access

        return true;
    }

    /**
     * Verifies the integrity of the B-Tree from this node downwards.
     *
     * @param null|Node   $node   The node to verify. If null, starts from the current node.
     * @param null|string $minKey the minimum key value for the current node
     * @param null|string $maxKey the maximum key value for the current node
     *
     * @return bool returns true if the subtree is valid, false otherwise
     */
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

    /**
     * Looks up the child node that should contain the specified key.
     *
     * @param string $key the key to look up
     *
     * @return null|Node the child node, or null if no suitable child is found
     *
     * @throws \RuntimeException if called on a leaf node
     */
    private function lookupChild(string $key): ?Node
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

        return null;
    }

    /**
     * Splits the current node into two nodes and promotes the median key to the parent.
     */
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
        foreach ($newNode->children as $key => $ptr) {
            if (isset(self::$nodeCache[$ptr])) {
                self::$nodeCache[$ptr]->parentNode = $newNode; // Update parent node for cached nodes
            }
        }
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

    /**
     * Caches the specified node.
     *
     * @param self $node the node to cache
     *
     * @throws \RuntimeException if the node has an invalid pointer
     */
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
