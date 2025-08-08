<?php

declare(strict_types=1);

namespace Hazaar\Util;

use Hazaar\Util\BTree\Node;
use Hazaar\Util\BTree\NodeType;

class BTree
{
    private const VERSION_STRING = '1.0';
    private Version $version;
    private int $slotSize = 4; // Size of each node slot in bytes

    private string $filePath;

    /**
     * @var resource
     */
    private mixed $file;

    private Node $rootNode;

    public function __construct(string $filePath)
    {
        $this->version = new Version(self::VERSION_STRING);
        $this->filePath = $filePath;
        // Initialize the BTree file if it does not exist
        $file = fopen($this->filePath, 'c+');
        if (false === $file) {
            throw new \RuntimeException("Could not open BTree file: {$this->filePath}");
        }
        $this->file = $file;
        // Load the root node from the file
        $this->loadRootNode();
    }

    public function __destruct()
    {
        // Save the BTree to file on destruction
    }

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

    public function get(string $key): mixed
    {
        return $this->rootNode->get($key);
    }

    public function remove(string $key): bool
    {
        $ptr = $this->rootNode->ptr;
        $this->rootNode->remove($key);
        if ($ptr !== $this->rootNode->ptr) {
            $this->writeHeader();
        }

        return true;
    }

    public function compact(): bool
    {
        // Implementation for compacting the BTree file
        return false;
    }

    private function loadRootNode(): bool
    {
        $headerSize = 10; // Size of the header in bytes
        // Load the root node from the file
        fseek($this->file, 0);
        $buffer = fread($this->file, $headerSize);
        if ($buffer && $headerSize === strlen($buffer)) {
            $header = unpack('Smajor/Sminor/Sslot/Lptr', $buffer);
            if ($this->version->getMajor() !== $header['major']) {
                return false;
            }
            $this->slotSize = $header['slot'];
            $this->rootNode = new Node($this->file, $header['ptr']);

            return true;
        }

        $this->rootNode = Node::create($this->file, $this->slotSize, NodeType::LEAF);
        $this->rootNode->write($headerSize + 1);

        return $this->writeHeader();
    }

    private function writeHeader(): bool
    {
        fseek($this->file, 0);

        return false !== fwrite($this->file, pack(
            'SSSL',
            $this->version->getMajor(),
            $this->version->getMinor(),
            $this->slotSize,
            $this->rootNode->ptr
        ));
    }
}
