<?php

declare(strict_types=1);

namespace Hazaar\Util;

use Hazaar\Util\BTree\Node;
use Hazaar\Util\BTree\NodeType;

class BTree
{
    private const VERSION_STRING = 'V1.0';
    private const HEADER_SIZE = 12; // 64 bit pointer + 4 byte header
    private int $slotSize = 8; // Size of each node slot in bytes

    private string $filePath;

    /**
     * @var resource
     */
    private mixed $file;

    private Node $rootNode;

    public function __construct(string $filePath)
    {
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

    public function set(string $key, mixed $value): bool
    {
        return $this->rootNode->set($key, $value);
    }

    public function get(string $key): mixed
    {
        return $this->rootNode->get($key);
    }

    public function remove(string $key): bool
    {
        // Implementation for removing a key-value pair from the BTree
        return false;
    }

    public function compact(): bool
    {
        // Implementation for compacting the BTree file
        return false;
    }

    private function loadRootNode(): void
    {
        // Load the root node from the file
        fseek($this->file, 0);
        $buffer = fread($this->file, self::HEADER_SIZE);
        if ($buffer && self::HEADER_SIZE === strlen($buffer)) {
            $header = unpack('a4ver/Sslot/Lptr', $buffer);
            if (self::VERSION_STRING !== $header['ver']) {
                throw new \RuntimeException("Invalid BTree file header: {$this->filePath}");
            }
            $this->slotSize = $header['slot'];
            $this->rootNode = new Node($this->file, $header['ptr']);
        } else {
            $this->rootNode = Node::create($this->file, $this->slotSize, NodeType::LEAF);
            $headerPtr = self::HEADER_SIZE + 1;
            fwrite($this->file, pack('a4SL', self::VERSION_STRING, $this->slotSize, $headerPtr)); // Write a zero header if the file is empty
            $this->rootNode->write($headerPtr);
        }
    }
}
