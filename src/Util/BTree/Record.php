<?php

namespace Hazaar\Util\BTree;

class Record
{
    public string $key;
    public mixed $value;
    public int $ptr;
    public Node $parentNode;

    /**
     * @var resource
     */
    private mixed $file;

    public static function create(Node $parentNode): self
    {
        if (!is_resource($parentNode->file)
            || 'stream' !== get_resource_type($parentNode->file)) {
            throw new \InvalidArgumentException('Invalid file resource provided.');
        }
        $record = new self();
        $record->parentNode = $parentNode;
        $record->file = $parentNode->file;

        return $record;
    }

    public function read(?int $ptr = null): mixed
    {
        if (isset($this->ptr) && $ptr === $this->ptr && isset($this->value)) {
            return $this->value;
        }
        if (null === $ptr) {
            $ptr = $this->ptr;
        }
        /*
         * Seek to the record's position but skip the max record length which is
         * used to check if an in-place update can be performed.
         *
         * We only need to read the actual record length, which is stored at the
         * start of the record, and then read the data.
         */
        fseek($this->file, $ptr + Node::NODE_PTR_SIZE);
        $lengthBuffer = fread($this->file, Node::NODE_PTR_SIZE);
        if (false === $lengthBuffer || Node::NODE_PTR_SIZE !== strlen($lengthBuffer)) {
            return false;
        }
        $recordLength = unpack('L', $lengthBuffer)[1];
        $data = fread($this->file, $recordLength);
        if (false === $data || strlen($data) !== $recordLength) {
            return false;
        }
        $this->ptr = $ptr;

        return $this->value = unserialize($data);
    }

    public function write(string $key, mixed $value): bool
    {
        $data = serialize($value);
        $dataLength = strlen($data);
        $maxRecordLength = 0;
        // If the key already exists, update its value
        if (isset($this->ptr) && $this->ptr > 0) {
            fseek($this->file, $this->ptr);
            $maxRecordLength = unpack('L', fread($this->file, Node::NODE_PTR_SIZE))[1];
        }
        if ($dataLength > $maxRecordLength) {
            fseek($this->file, 0, SEEK_END);
            $this->ptr = ftell($this->file);
            $maxRecordLength = (($dataLength + 15) & ~15);
            fwrite($this->file, pack('L', $maxRecordLength));
        }
        fwrite($this->file, pack('L', $dataLength));
        fwrite($this->file, $data);
        $this->key = $key;
        $this->value = $value;

        return true;
    }
}
