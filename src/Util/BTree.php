<?php

declare(strict_types=1);

namespace Hazaar\Util;

use Hazaar\Util\BTree\Node;
use Hazaar\Util\BTree\NodeType;

class BTree
{
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
     * @param int    $keySize  Size of the key in bytes. Default is 32.
     * @param int    $slotSize Size of the slot in bytes. Default is 16.
     *
     * @throws \RuntimeException if the BTree file cannot be opened
     */
    public function __construct(string $filePath, int $keySize = 32, int $slotSize = 16)
    {
        $this->version = new Version(self::VERSION_STRING);
        $this->filePath = $filePath;
        $this->slotSize = $slotSize;
        $this->keySize = $keySize;
        // Initialize the BTree file if it does not exist
        $file = fopen($this->filePath, 'c+');
        if (false === $file) {
            throw new \RuntimeException("Could not open BTree file: {$this->filePath}");
        }
        $this->file = $file;
        // Load the root node from the file
        $this->loadRootNode();
    }

    /**
     * Resets the B-Tree by reloading the root node from the file.
     *
     * @return bool returns true on success
     */
    public function reset(): bool
    {
        return $this->loadRootNode();
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
        $ptr = $this->rootNode->ptr;
        $this->rootNode->set($key, $value);
        if ($ptr !== $this->rootNode->ptr) {
            $this->writeHeader();
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
        return $this->rootNode->get($key);
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
        $ptr = $this->rootNode->ptr;
        $this->rootNode->remove($key);
        if ($ptr !== $this->rootNode->ptr) {
            $this->writeHeader();
        }

        return true;
    }

    /**
     * Compacts the B-Tree file to reduce its size.
     *
     * @return bool returns true on success
     */
    public function compact(): bool
    {
        // Implementation for compacting the BTree file
        return false;
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
        $this->rootNode->write(10); // Write the header size

        return $this->writeHeader();
    }

    /**
     * Return the entire B-Tree as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $result = [];
        $this->rootNode->toArray($result);

        return $result;
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
    private function loadRootNode(): bool
    {
        $headerSize = 10; // Size of the header in bytes
        // Load the root node from the file
        fseek($this->file, 0);
        $buffer = fread($this->file, $headerSize);
        if ($buffer && $headerSize === strlen($buffer)) {
            $header = unpack('Smajor/Sminor/Sslot/Skeys/Lptr', $buffer);
            if ($this->version->getMajor() !== $header['major']) {
                return false;
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
        $this->rootNode->write($headerSize + 1);

        return $this->writeHeader();
    }

    /**
     * Writes the header to the B-Tree file.
     *
     * @return bool returns true on success
     */
    private function writeHeader(): bool
    {
        fseek($this->file, 0);

        return false !== fwrite($this->file, pack(
            'SSSSL',
            $this->version->getMajor(),
            $this->version->getMinor(),
            $this->slotSize,
            $this->keySize,
            $this->rootNode->ptr
        ));
    }
}
