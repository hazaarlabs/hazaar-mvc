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

    /**
     * Creates a new Record instance.
     *
     * @param Node   $parentNode the parent node of the record
     * @param string $key        the key of the record
     *
     * @return self the new Record instance
     *
     * @throws \InvalidArgumentException if the file resource in the parent node is invalid
     */
    public static function create(Node $parentNode, string $key): self
    {
        if (!is_resource($parentNode->file)
            || 'stream' !== get_resource_type($parentNode->file)) {
            throw new \InvalidArgumentException('Invalid file resource provided.');
        }
        $record = new self();
        $record->parentNode = $parentNode;
        $record->file = $parentNode->file;
        $record->key = $key;

        return $record;
    }

    /**
     * Reads the record's value from the file.
     *
     * If the value is already in memory, it is returned directly. Otherwise, it seeks to the
     * record's position in the file, reads its length and data, unserializes the data,
     * and returns the value.
     *
     * @param null|int $ptr the pointer to the record in the file. If null, uses the current record's pointer.
     *
     * @return mixed the value of the record, or false on failure
     */
    public function read(?int $ptr = null): mixed
    {
        if (isset($this->ptr) && $ptr === $this->ptr && isset($this->value)) {
            return $this->value;
        }
        if (null === $ptr) {
            $ptr = $this->ptr;
        }
        if (!flock($this->file, LOCK_SH)) {
            return false;
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
        flock($this->file, LOCK_UN);

        return $this->value = unserialize($data);
    }

    /**
     * Writes the record's key and value to the file.
     *
     * Serializes the value and writes it to the file. If the record already exists and the new
     * data fits within the allocated space, it updates the record in-place. Otherwise, it
     * appends the new record to the end of the file and updates the pointer.
     *
     * @param string $key   the key of the record
     * @param mixed  $value the value of the record
     *
     * @return bool returns true on success, false on failure
     */
    public function write(string $key, mixed $value): bool
    {
        if (!flock($this->file, LOCK_EX)) {
            return false;
        }
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
        flock($this->file, LOCK_UN);
        $this->key = $key;
        $this->value = $value;

        return true;
    }

    /**
     * Moves the record to a new leaf node.
     *
     * This method updates the record's parent node and file handle, resets its pointer,
     * writes the record to the new location in the file, and adds the record to the new leaf node.
     *
     * @param Node $leaf the new leaf node to move the record to
     *
     * @return bool returns true on success
     */
    public function moveTo(Node $leaf): bool
    {
        $this->parentNode = $leaf;
        $this->file = $leaf->file;
        $this->ptr = 0;
        $this->write($this->key, $this->value);

        return $leaf->addRecord($this);
    }
}
