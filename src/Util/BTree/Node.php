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
     * @var array<int,array<int,self>>
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
     * Constructs a new BTree node.
     *
     * @param resource $file     the file resource or handler associated with the node
     * @param null|int $ptr      optional pointer to the node's position in the file
     * @param int      $slotSize the size of each slot in the node (minimum 4)
     * @param int      $keySize  the size of each key in the node
     *
     * @throws \InvalidArgumentException if the slot size is less than 4
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
     * Resets the static caches for nodes and records.
     *
     * This method clears both the node cache and the record cache by
     * setting their respective static arrays to empty. Use this to
     * ensure that cached data is discarded and fresh data will be loaded
     * on subsequent accesses.
     */
    public function resetCache(): void
    {
        unset(self::$nodeCache[(int) $this->file], self::$nodeCache[(int) $this->file]);
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
        if (!flock($this->file, LOCK_SH)) {
            return false; // Lock the file for shared access
        }
        fseek($this->file, $this->ptr);
        $typeBuffer = fread($this->file, 1);
        if (false === $typeBuffer || 1 !== strlen($typeBuffer)) {
            flock($this->file, LOCK_UN); // Unlock the file before returning

            return false;
        }
        $this->nodeType = NodeType::from(unpack('a', $typeBuffer)[1]);
        $lengthBuffer = fread($this->file, self::NODE_PTR_SIZE);
        if (false === $lengthBuffer || self::NODE_PTR_SIZE !== strlen($lengthBuffer)) {
            flock($this->file, LOCK_UN); // Unlock the file before returning

            return false;
        }
        $this->length = unpack('L', $lengthBuffer)[1];
        $data = fread($this->file, $this->length);
        flock($this->file, LOCK_UN); // Unlock the file after reading
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
        if (!flock($this->file, LOCK_EX)) {
            return false; // Lock the file for exclusive access
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
        $result = fwrite(
            stream: $this->file,
            data: str_pad(
                string: $data,
                length: $this->length,
                pad_string: "\0",
                pad_type: STR_PAD_RIGHT
            ),
            length: $this->length
        );
        flock($this->file, LOCK_UN); // Unlock the file after writing
        if (false === $result) {
            return false;
        }
        $this->ptr = $ptr;
        $this->cacheNode($this); // Cache the node after writing

        return true;
    }

    /**
     * Adds a new key-value pair to the B-tree node.
     *
     * This method creates a new leaf node, sets the provided key and value,
     * and adds the node as a child to the current node. Only internal nodes
     * can have children; attempting to add a record to a non-leaf node will
     * throw a RuntimeException.
     *
     * @param string $key   the key to add to the node
     * @param mixed  $value the value associated with the key
     *
     * @return bool returns true on successful addition
     *
     * @throws \RuntimeException if the node is not an internal node
     */
    public function add(string $key, mixed $value): bool
    {
        if (NodeType::INTERNAL !== $this->nodeType) {
            throw new \RuntimeException('Cannot add record to a non-leaf node.');
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
            throw new \RuntimeException('Cannot set value in a non-leaf node.');
        }
        $record = self::$recordCache[$key] ?? Record::create($this, $key);
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
        if (NodeType::LEAF !== $this->nodeType) {
            throw new \RuntimeException('Cannot only get value from a leaf node.');
        }
        if (!isset($this->children[$key])) {
            return null;
        }
        $record = self::$recordCache[$key] ?? Record::create($this, $key);

        return $record->read($this->children[$key]);
    }

    /**
     * Checks if the specified key exists in the node.
     *
     * @param string $key the key to check
     *
     * @return bool returns true if the key exists, false otherwise
     */
    public function has(string $key): bool
    {
        if (NodeType::LEAF !== $this->nodeType) {
            throw new \RuntimeException('Cannot check existence in a non-leaf node.');
        }
        if (!isset($this->children[$key])) {
            return false; // Key does not exist
        }

        return true;
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
            throw new \RuntimeException('Cannot only get value from a leaf node.');
        }
        if (!isset($this->children[$key])) {
            return false; // Key does not exist
        }
        unset($this->children[$key]);
        $this->write();

        return true;
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
            $key = (string) $key;
            if (null !== $minKey && $key <= $minKey) {
                return false; // Key is not greater than minKey
            }
            if (null !== $maxKey && $key > $maxKey) {
                return false; // Key is not less than or equal to maxKey
            }
            if (NodeType::INTERNAL === $node->nodeType) {
                $childNode = self::$nodeCache[(int) $this->file][$ptr] ?? new self($node->file, $ptr);
                if (!$this->verifyTree($childNode, $childMinKey, $key)) {
                    return false;
                }
                $childMinKey = $key;
            }
        }

        return true;
    }

    /**
     * Counts the total number of child nodes in the subtree rooted at this node.
     *
     * If the node is a leaf, returns the number of its children directly.
     * Otherwise, recursively counts the children of all descendant nodes.
     *
     * @return int the total count of child nodes
     */
    public function countRecords(): int
    {
        if (NodeType::LEAF === $this->nodeType) {
            return count($this->children);
        }
        $count = 0;
        foreach ($this->children as $ptr) {
            $childNode = self::$nodeCache[(int) $this->file][$ptr] ?? new self($this->file, $ptr);
            $count += $childNode->countRecords(); // Recursively count children
        }

        return $count;
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
    public function lookupLeafNode(string $key): ?Node
    {
        if (NodeType::LEAF === $this->nodeType) {
            throw new \RuntimeException('Cannot lookup child in a leaf node.');
        }
        foreach ($this->children as $childKey => $childPtr) {
            if (!($key <= $childKey)) {
                continue;
            }
            // Return the child node that should contain the key
            $node = self::$nodeCache[(int) $this->file][$childPtr] ?? new self($this->file, $childPtr);
            $node->parentNode = $this;

            if (NodeType::LEAF === $node->nodeType) {
                // If the child node is a leaf, return it directly
                return $node;
            }

            return $node->lookupLeafNode($key);
        }

        return null;
    }

    /**
     * Returns a generator that yields all leaf nodes in the subtree rooted at this node.
     *
     * This method traverses the B-tree and yields each leaf node it encounters.
     * It can be used to iterate over all leaf nodes in the B-tree.
     *
     * @return \Generator<self> yields leaf nodes
     */
    public function leaf(): \Generator
    {
        if (NodeType::LEAF === $this->nodeType) {
            yield $this; // Yield the current leaf node

            return;
        }
        foreach ($this->children as $childPtr) {
            $childNode = self::$nodeCache[(int) $this->file][$childPtr] ?? new self($this->file, $childPtr);
            if (NodeType::LEAF === $childNode->nodeType) {
                yield $childNode; // Yield leaf nodes
            } else {
                yield from $childNode->leaf(); // Recursively yield leaf nodes from child nodes
            }
        }
    }

    /**
     * Splits the current node into two nodes when it becomes full.
     *
     * This method is called when the number of children in the current node exceeds its slot size.
     * It divides the children into two halves, creates a new node to store the first half,
     * and keeps the second half in the current node. The median key is then promoted to the parent node.
     * If the current node is the root, a new root is created.
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
            if (isset(self::$nodeCache[(int) $this->file][$ptr])) {
                self::$nodeCache[(int) $this->file][$ptr]->parentNode = $newNode; // Update parent node for cached nodes
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
        if (!array_key_exists((int) $this->file, self::$nodeCache)) {
            self::$nodeCache[(int) $this->file] = []; // Initialize cache for this file if not already set
        }
        if (array_key_exists($node->ptr, self::$nodeCache[(int) $this->file])) {
            return; // Node is already cached
        }
        while (count(self::$nodeCache[(int) $this->file]) >= $node->cacheSize) {
            array_shift(self::$nodeCache[(int) $this->file]); // Remove the oldest cached node if cache size exceeds limit
        }
        self::$nodeCache[(int) $this->file][$node->ptr] = $node; // Cache the node for future access
    }
}
