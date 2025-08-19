<?php

declare(strict_types=1);

namespace Hazaar\Util;

use Hazaar\Util\BTree\Node;
use Hazaar\Util\BTree\NodeType;
use Hazaar\Util\BTree\Record;

/**
 * BTree class provides a B-Tree implementation for storing key-value pairs.
 * It supports basic operations like insert, delete, and search.
 *
 * @implements \IteratorAggregate<string, mixed>
 */
class BTree implements \IteratorAggregate
{
    private const BTREE_HEADER_SIZE = 12; // Size of the header in bytes
    private const VERSION_STRING = '1.0';
    private Version $version;
    private int $slotSize = 16; // Size of each node slot in bytes
    private int $keySize = 32; // Maximum size of each key in bytes

    private string $filePath;

    /**
     * @var resource
     */
    private mixed $file;

    private Node $rootNode;
    private bool $readOnly = false;

    /**
     * Constructs a new BTree instance.
     *
     * Initializes the BTree with the specified file path, key size, and slot size.
     * If the BTree file does not exist, it will be created. Loads the root node from the file.
     *
     * The header will be written to the file in the format:
     * - Major version (2 bytes)
     * - Minor version (2 bytes)
     * - Slot size (2 bytes)
     * - Key size (2 bytes)
     * - Pointer to the root node (4 bytes)
     *
     * If the file already exists, it will read the header to determine the BTree's configuration.
     *
     * @param string $filePath path to the BTree file
     * @param bool   $readOnly if true, the BTree will be opened in read-only mode; otherwise, it will be opened in read-write mode
     * @param int    $keySize  Size of the key in bytes. Default is 32.
     * @param int    $slotSize Size of the slot in bytes. Default is 16.
     *
     * @throws \RuntimeException if the BTree file cannot be opened
     */
    public function __construct(string $filePath, bool $readOnly = false, int $keySize = 32, int $slotSize = 16)
    {
        $this->version = new Version(self::VERSION_STRING);
        $this->filePath = $filePath;
        $this->slotSize = $slotSize;
        $this->keySize = $keySize;
        // Initialize the BTree file if it does not exist
        $mode = ($this->readOnly = $readOnly) ? 'rb' : 'c+b';
        $file = fopen($this->filePath, $mode);
        if (false === $file) {
            throw new \RuntimeException("Could not open BTree file: {$this->filePath}");
        }
        $this->file = $file;
        // Load the root node from the file
        $this->readHeader();
    }

    /**
     * Destructor for the BTree class.
     *
     * Ensures that the BTree file resource is closed when the object is destroyed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Closes the BTree file resource.
     */
    public function close(): void
    {
        if (isset($this->file) && is_resource($this->file)) {
            fclose($this->file);
        }
        if (isset($this->rootNode)) {
            $this->rootNode->resetCache();
        }
        unset($this->file, $this->rootNode);
    }

    /**
     * Resets the B-Tree by reloading the root node from the file.
     *
     * @return bool returns true on success
     */
    public function reset(): bool
    {
        if (isset($this->rootNode)) {
            $this->rootNode->resetCache();
        }

        return $this->readHeader();
    }

    /**
     * Sets the value for the specified key in the B-Tree.
     *
     * @param string $key   the key to set in the B-Tree
     * @param mixed  $value the value to associate with the key
     *
     * @return bool returns true on success
     */
    public function set(string $key, mixed $value): bool
    {
        if ($this->readOnly) {
            return false; // Cannot set values in read-only mode
        }
        $ptr = $this->rootNode->ptr;
        $leafNode = $this->rootNode->lookupLeafNode($key);
        if ($leafNode) {
            $leafNode->set($key, $value);
        } else {
            $this->rootNode->add($key, $value);
        }
        if ($ptr !== $this->rootNode->ptr) {
            $this->writeHeader($this->file);
        }

        return true;
    }

    /**
     * Gets the value for the specified key from the B-Tree.
     *
     * @param string $key the key to get from the B-Tree
     *
     * @return mixed the value associated with the key, or null if the key does not exist
     */
    public function get(string $key): mixed
    {
        $leafNode = $this->rootNode->lookupLeafNode($key);
        if ($leafNode) {
            return $leafNode->get($key);
        }

        return null;
    }

    /**
     * Removes the specified key from the B-Tree.
     *
     * @param string $key the key to remove from the B-Tree
     *
     * @return bool returns true on success
     */
    public function remove(string $key): bool
    {
        $leafNode = $this->rootNode->lookupLeafNode($key);
        if ($leafNode) {
            return $leafNode->remove($key);
        }

        return true;
    }

    /**
     * Compacts the B-Tree file to reduce its size.
     *
     * @param float $fillFactor the fill factor for compacting the B-Tree, default is 0.5  This is used
     *                          to determine how full the nodes should be after compaction.
     *                          * A fill factor of 0.5 means that nodes will be 50% full after compaction.
     *                          * A fill factor of 0.8 means that nodes will be 80% full after compaction.
     *
     * @return bool returns true on success
     */
    public function compact(float $fillFactor = 0.5): bool
    {
        if ($this->readOnly || 0 === $this->count()) {
            return false; // Cannot compact in read-only mode
        }
        $tmpFilePath = $this->filePath.'.tmp';
        $tmpFile = fopen($tmpFilePath, 'c+b');
        if (false === $tmpFile) {
            throw new \RuntimeException("Could not open temporary BTree file: {$tmpFilePath}");
        }
        $this->writeHeader($tmpFile);
        $fillCount = (int) ($this->slotSize * $fillFactor);
        $newLeafNode = null;
        $nodeTree = [];
        foreach ($this->rootNode->leaf() as $leafNode) {
            foreach ($leafNode->children as $key => $recordPtr) {
                // Create a new leaf node if it doesn't exist
                if (!$newLeafNode) {
                    $newLeafNode = Node::create(
                        file: $tmpFile,
                        type: NodeType::LEAF,
                        slotSize: $this->slotSize,
                        keySize: $this->keySize
                    );
                }
                // Create a new record and move it to the new leaf node
                $record = Record::create($leafNode, (string) $key);
                $record->read($recordPtr);
                $record->moveTo($newLeafNode);
                // Check if the new leaf node is full
                if (count($newLeafNode->children) >= $fillCount) {
                    // If the new top level internal node does not exist, create it
                    if (!isset($nodeTree[0])) {
                        $nodeTree[0] = Node::create(
                            file: $tmpFile,
                            type: NodeType::INTERNAL,
                            slotSize: $this->slotSize,
                            keySize: $this->keySize
                        );
                    }
                    // Add the new leaf node to the new internal node
                    $nodeTree[0]->addNode($newLeafNode);
                    $newLeafNode = null;
                    // Check if the new internal node is full and propagate upwards
                    for ($i = 0; $i < count($nodeTree); ++$i) {
                        // If the current node is not set or does not have enough children, skip it
                        if (!($nodeTree[$i] && count($nodeTree[$i]->children) >= $fillCount)) {
                            continue;
                        }
                        // If the new internal node is full, add it to the node tree
                        // If the next internal node does not exist, create it
                        if (!isset($nodeTree[$i + 1])) {
                            $nodeTree[$i + 1] = Node::create(
                                file: $tmpFile,
                                type: NodeType::INTERNAL,
                                slotSize: $this->slotSize,
                                keySize: $this->keySize
                            );
                        }
                        // Add the current internal node to the next internal node
                        // This effectively propagates the full internal node upwards in the tree
                        $nodeTree[$i + 1]->addNode($nodeTree[$i]);
                        $nodeTree[$i] = null;
                    }
                }
            }
        }
        // If there is a new leaf node that has not been added to the node tree, add it now
        if ($newLeafNode) {
            $nodeTree[0]->addNode($newLeafNode);
        }
        // Now make sure all internal nodes are propagated upwards
        // This is necessary to ensure that the tree structure is maintained
        // and that the root node is correctly set
        for ($i = 0; $i < count($nodeTree) - 1; ++$i) {
            // If the next internal node does not exist, create it
            if (!isset($nodeTree[$i + 1])) {
                $nodeTree[$i + 1] = Node::create(
                    file: $tmpFile,
                    type: NodeType::INTERNAL,
                    slotSize: $this->slotSize,
                    keySize: $this->keySize
                );
            }
            // Add the current internal node to the next internal node
            // This effectively propagates the full internal node upwards in the tree
            $nodeTree[$i + 1]->addNode($nodeTree[$i]);
        }
        $newRootNode = array_pop($nodeTree);
        $this->writeHeader($tmpFile, $newRootNode);
        // Close the old file and reset the root node cache
        $this->rootNode->resetCache();
        fclose($this->file);
        fclose($tmpFile);
        // Rename the temporary file to the original file path
        if (!rename($tmpFilePath, $this->filePath)) {
            throw new \RuntimeException("Could not rename temporary BTree file to: {$this->filePath}");
        }
        unset($this->rootNode);
        $this->file = fopen($this->filePath, 'c+b');

        return $this->readHeader();
    }

    /**
     * Returns the total number of elements in the B-tree.
     *
     * @return int the count of elements stored in the tree
     */
    public function count(): int
    {
        return $this->rootNode->countRecords();
    }

    /**
     * Returns a generator that yields all records in the B-Tree.
     *
     * This method traverses the B-Tree and yields each record's key and value.
     *
     * @return \Generator<string, mixed> a generator yielding key-value pairs from the B-Tree
     */
    public function getIterator(): \Generator
    {
        foreach ($this->rootNode->leaf() as $node) {
            foreach ($node->children as $key => $recordPtr) {
                $record = Record::create($node, (string) $key);

                yield $record->key => $record->read($recordPtr);
            }
        }
    }

    /**
     * Empties the B-Tree by creating a new root node.
     *
     * @return bool returns true on success
     */
    public function empty(): bool
    {
        $this->rootNode = Node::create(
            file: $this->file,
            type: NodeType::INTERNAL,
            slotSize: $this->slotSize,
            keySize: $this->keySize
        );
        $this->rootNode->write(self::BTREE_HEADER_SIZE); // Write the header size

        return $this->writeHeader($this->file);
    }

    /**
     * Return the entire B-Tree as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * Verifies the integrity of the B-Tree.
     *
     * @return bool returns true if the B-Tree is valid, false otherwise
     */
    public function verify(): bool
    {
        return $this->rootNode->verifyTree();
    }

    /**
     * Loads the root node from the B-Tree file.
     *
     * @return bool returns true on success
     */
    private function readHeader(): bool
    {
        // Load the root node from the file
        fseek($this->file, 0);
        $buffer = fread($this->file, self::BTREE_HEADER_SIZE);
        if ($buffer && self::BTREE_HEADER_SIZE === strlen($buffer)) {
            $header = unpack('Smajor/Sminor/Sslot/Skeys/Lptr', $buffer);
            if ($this->version->getMajor() !== $header['major']) {
                throw new \RuntimeException(
                    "BTree version mismatch: expected {$this->version->getMajor()}, got {$header['major']}"
                );
            }
            $this->slotSize = $header['slot'];
            $this->keySize = $header['keys'];
            $this->rootNode = new Node($this->file, $header['ptr']);

            return true;
        }
        $this->rootNode = Node::create(
            file: $this->file,
            type: NodeType::INTERNAL,
            slotSize: $this->slotSize,
            keySize: $this->keySize
        );
        $this->rootNode->write(self::BTREE_HEADER_SIZE);

        return $this->writeHeader($this->file);
    }

    /**
     * Writes the header to the B-Tree file.
     *
     * @return bool returns true on success
     */
    private function writeHeader(mixed $file, ?Node $rootNode = null): bool
    {
        $header = pack(
            'SSSSL',
            $this->version->getMajor(),
            $this->version->getMinor(),
            $this->slotSize,
            $this->keySize,
            $rootNode->ptr ?? $this->rootNode->ptr ?? 0
        );
        fseek($file, 0);

        return false !== fwrite($file, $header);
    }
}
