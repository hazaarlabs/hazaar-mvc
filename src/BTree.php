<?php

declare(strict_types=1);

namespace Hazaar;

/**
 * B-Tree key/value database file access class.
 *
 * This class provides a high performance key/value storage mechanism that stores data to file. A B-tree is
 * a self-balancing tree data structure that keeps data sorted and allows searches, sequential access,
 * insertions, and deletions in logarithmic time.
 */
class BTree
{
    /**
     * Size of header.
     */
    public const SIZEOF_HEADER = 6;

    /**
     * Header that has to be at end of every file.
     */
    public const HEADER = "\xffbtree";

    /**
     * Maximum number of keys per node (do not even think about to change it).
     */
    public const NODE_SLOTS = 16;

    /**
     * Size of integer (pack type N).
     */
    public const SIZEOF_INT = 4;

    /**
     * This is key-value node.
     */
    public const KVNODE = 'kv';

    /**
     * This is key-pointer node.
     */
    public const KPNODE = 'kp';

    /**
     * Size of node chache.
     */
    public const NODECHACHE_SIZE = 64;

    /**
     * The file resource.
     */
    private ?File $file = null;

    /**
     * @var array<mixed> Node cache
     */
    private array $nodecache = [];

    private bool $read_only = false;

    /**
     * Use static method open() to get instance.
     */
    public function __construct(File|string $file, bool $read_only = false)
    {
        $this->file = null;
        $this->read_only = $read_only;

        if (!$this->open($file, $read_only)) {
            throw new Exception('Unable to open file: '.$file);
        }
    }

    /**
     * Open the file for access.
     */
    public function open(null|File|string $file = null, bool $read_only = false): bool
    {
        if (null === $file) {
            if (!$this->file) {
                throw new Exception('No file specified!');
            }
            if ($this->file->isOpen()) {
                return true;
            }
        } else {
            if ($this->file) {
                $this->file->close();
            }
            if (!$file instanceof File) {
                $file = new File($file);
            }
            $this->file = $file;
        }
        if ('local' !== $file->backend()) {
            throw new Exception('The BTree file class currently only supports the local file manager backend!', 400);
        }
        // Check if the file is too big.  The file size will be negative if PHP doesn't support the file.
        if ($this->file->exists() && $this->file->size() < 0) {
            throw new Exception('File is too large.  On 32-bit PHP only files up to 2GB in size are supported.');
        }
        $this->file->open((true === $this->read_only) ? 'rb' : 'a+b');
        // write default node if neccessary
        if (-1 === $this->file->seek(0, SEEK_END)) {
            $this->file->close();

            return false;
        }
        if (0 === $this->file->tell()) {
            if (!$this->file->lock(LOCK_EX)) {
                $this->file->close();

                return false;
            }
            $root = self::serialize(self::KVNODE, []);
            $to_write = pack('N', strlen($root)).$root;
            if ($this->file->write($to_write, strlen($to_write)) !== strlen($to_write) || !self::header($this->file, 0) || !$this->file->lock(LOCK_UN)) {
                $this->file->truncate(0);
                $this->file->close();

                return false;
            }
        }

        return true;
    }

    /**
     * Close the file.
     */
    public function close(): bool
    {
        if (!$this->file) {
            return false;
        }
        $this->file->close();
        unset($this->file);

        return true;
    }

    /**
     * The the B-Tree source file.
     */
    public function reset_btree_file(): bool
    {
        if (!$this->file) {
            return false;
        }
        $this->file->close();
        $this->file->unlink();

        return $this->open($this->file, $this->read_only);
    }

    /**
     * Get value by key.
     *
     * @param string $key The key to return data for
     *
     * @return mixed
     */
    public function get($key)
    {
        $lookup = $this->lookup($key);
        if (is_array($lookup)) {
            $leaf = end($lookup);
            if (null !== $leaf && isset($leaf[$key])) {
                return $leaf[$key];
            }
        }

        return null;
    }

    /**
     * Get all values where startkey <= key < endkey.
     *
     * To get all data, use:
     *
     * ```php
     * $values = $btree->range("\x00", "\xff");
     * ```
     *
     * @return array<mixed>
     */
    public function range(string $startkey, string $endkey): ?array
    {
        $start = $this->lookup($startkey);
        $end = $this->lookup($endkey);
        if (null === end($start) || null === end($end)) {
            return null;
        }
        $upnodes = [];
        while (!empty($start)) {
            $nodes = [];
            foreach (array_merge(array_shift($start), $upnodes, array_shift($end)) as $k => $v) {
                if (!(strcmp($k, $startkey) >= 0 && strcmp($k, $endkey) < 0)) {
                    continue;
                }
                if (empty($start)) {
                    $nodes[$k] = $v;
                } else {
                    list($node_type, $node) = $this->node($v);
                    if (null === $node_type || null === $node) {
                        return null;
                    }
                    $nodes = array_merge($nodes, $node);
                }
            }
            $upnodes = $nodes;
        }

        return $upnodes;
    }

    /**
     * Set value under given key.
     *
     * @param string $key   the key to store the value under
     * @param mixed  $value The value to store. A NULL value deletes given key.
     */
    public function set(string $key, mixed $value): bool
    {
        // Obtain an exclusive file lock
        if (!$this->file->lock(LOCK_EX)) {
            return false;
        }
        if (-1 === $this->file->seek(0, SEEK_END)) {
            $this->file->lock(LOCK_UN);

            return false;
        }
        if (($pos = $this->file->tell()) === -1) {
            $this->file->lock(LOCK_UN);

            return false;
        }
        $root = null;

        $cursor = $pos;
        // key lookup
        $lookup = $this->lookup($key);
        $node = array_pop($lookup);
        if (null === $node) {
            return false;
        }
        // change value
        $index = current(array_keys($node));
        $node_type = self::KVNODE;
        $new_index = null;
        if (null === $value) {
            unset($node[$key]);
        } else {
            $node[$key] = $value;
        }
        // traverse tree up
        do {
            if (count($node) <= (int)(self::NODE_SLOTS / 2) && !empty($lookup)) {
                $upnode = (array) array_pop($lookup);
                $new_index = current(array_keys($upnode));
                $sibling = $prev = [null, null];
                foreach ($upnode as $k => $v) {
                    if ($index === $k) {
                        $sibling = $prev;
                    } // left sibling
                    elseif ($index === $prev[0]) {
                        $sibling = [$k, $v];
                    } // right sibling
                    if (null !== $sibling[0]) {
                        list($sibling_type, $sibling_node) = $this->node($sibling[1]);
                        if (null === $sibling_type || null === $sibling_node) {
                            $this->file->truncate($pos);
                            $this->file->lock(LOCK_UN);

                            return false;
                        }
                        $node = array_merge($node, $sibling_node);
                        unset($upnode[$sibling[0]]);
                    }
                    $prev = [$k, $v];
                    $sibling = [null, null];
                }
                array_push($lookup, $upnode);
            }
            ksort($node, SORT_STRING);
            if (count($node) <= self::NODE_SLOTS) {
                $nodes = [$node];
            } else {
                $nodes = array_chunk($node, (int) ceil(count($node) / ceil(count($node) / self::NODE_SLOTS)), true);
            }
            $upnode = array_merge([], (array) array_pop($lookup));
            if (null === $new_index) {
                $new_index = current(array_keys($upnode));
            }
            unset($upnode[$index]);
            foreach ($nodes as $_) {
                $serialized = self::serialize($node_type, $_);
                $to_write = pack('N', strlen($serialized)).$serialized;
                if ($this->file->write($to_write, strlen($to_write)) !== strlen($to_write)) {
                    $this->file->truncate($pos);
                    $this->file->lock(LOCK_UN);

                    return false;
                }
                $upnode[current(array_keys($_))] = $cursor;
                $cursor += strlen($to_write);
            }
            $node_type = self::KPNODE;
            $index = $new_index;
            $new_index = null;
            if (count($upnode) <= 1) {
                $root = current(array_values($upnode));

                break;
            }
            array_push($lookup, $upnode);
        } while ($node = array_pop($lookup));
        // Write root header to the current database file
        if (!($this->file->flush() && self::header($this->file, $root))) {
            $this->file->truncate($pos);
            $this->file->lock(LOCK_UN);

            return false;
        }
        $this->file->lock(LOCK_UN);

        return true;
    }

    public function remove(string $key): bool
    {
        return $this->set($key, null);
    }

    /**
     * Check if a given key exists in the database.
     */
    public function has(string $key): bool
    {
        return null !== $this->get($key);
    }

    /**
     * Get a list of all available keys in the database.
     *
     * Warning: Unlike a search this will hit almost every part of the database file and can be a bit slow.
     *
     * @return array<string> An array of available keys
     */
    public function keys(): array
    {
        $keys = [];
        if (is_array($leaves = $this->leaves())) {
            foreach ($leaves as $p) {
                list(, $leaf) = $this->node($p);
                $keys = array_merge($keys, array_keys($leaf));
            }
        }

        return $keys;
    }

    /**
     * Get positions of all leaves.
     *
     * @return array<int> pointers to leaves; null on failure
     */
    public function leaves(): ?array
    {
        if (($root = $this->roothunt()) === null) {
            return null;
        }

        return $this->leafhunt($root);
    }

    /**
     * Remove old nodes from BTree file.
     *
     * @return bool true if everything went well; false otherwise
     */
    public function compact()
    {
        $compact_file = new File(uniqid($this->file.'~', true));
        if (!$compact_file->open('a+b')) {
            return false;
        }
        if (!((-1 !== $compact_file->seek(0, SEEK_END)
            && ($root = $this->copyto($compact_file)) !== null)
            && false !== self::header($compact_file, $root)
            && $compact_file->flush()
            && $compact_file->close()
            && $this->file->close()
            && $this->file->unlink()
            && @$compact_file->rename((string) $this->file))) { // will not work under windows, sorry
            $compact_file->close();
            @$compact_file->unlink();
            dump(error_get_last());

            return false;
        }
        $this->nodecache = [];
        $this->file->close();
        $this->file = $compact_file;
        $this->file->open('a+b');

        return true;
    }

    public function dropCache(): void
    {
        $this->nodecache = [];
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->range("\x00", "\xff");
    }

    /**
     * Look up a key.
     *
     * @param array<mixed> $node
     *
     * @return array<mixed>|bool traversed nodes
     */
    private function lookup(string $key, ?string $node_type = null, ?array $node = null): array|bool
    {
        if (!$this->file->lock(LOCK_SH)) {
            return false;
        }
        if (null === $node_type || null === $node) {
            list($node_type, $node) = $this->root();
        }
        if (null === $node_type || null === $node) {
            return [null];
        }
        $ret = [];
        do {
            array_push($ret, $node);
            if (self::KVNODE === $node_type) {
                $node = null;
            } else {
                $keys = array_keys($node);
                $l = 0;
                $r = count($keys);
                while ($l < $r) {
                    $i = $l + (int)(($r - $l) / 2);
                    if (strcmp($keys[$i], $key) < 0) {
                        $l = $i + 1;
                    } else {
                        $r = $i;
                    }
                }
                $l = max(0, $l + ($l >= count($keys) ? -1 : (strcmp($keys[$l], $key) <= 0 ? 0 : -1)));
                list($node_type, $node) = $this->node($node[$keys[$l]]);
                if (null === $node_type || null === $node) {
                    return [null];
                }
            }
        } while (null !== $node);
        $this->file->lock(LOCK_UN);

        return $ret;
    }

    /**
     * Find positions of all leaves.
     *
     * @param int $p pointer to node
     *
     * @return array<int> pairs of (leaf, pointer); null on failure
     */
    private function leafhunt(int $p): ?array
    {
        list($node_type, $node) = $this->node($p);
        if (null === $node_type || null === $node) {
            return null;
        }
        $nodes = [[$node_type, $node, $p]];
        $ret = [];
        do {
            $new_nodes = [];
            foreach ($nodes as $_) {
                list($node_type, $node, $p) = $_;
                if (self::KVNODE === $node_type) {
                    $ret[current(array_keys($node))] = $p;
                } else {
                    foreach ($node as $i) {
                        list($child_type, $child) = $this->node($i);
                        if (null === $child_type || null === $child) {
                            return null;
                        }
                        if (self::KVNODE === $child_type) {
                            $ret[current(array_keys($child))] = $i;
                        } else {
                            $new_nodes[] = [$child_type, $child, $i];
                        }
                    }
                }
            }
            $nodes = $new_nodes;
        } while (count($nodes) > 1);

        return $ret;
    }

    /**
     * Copy node from opened file to another.
     *
     * @param File         $to   The file to copy everything to
     * @param array<mixed> $node
     *
     * @return int new pointer to copied node;
     */
    private function copyto(File $to, ?string $node_type = null, ?array $node = null): ?int
    {
        $p = false;
        if (null === $node_type || null === $node) {
            list($node_type, $node) = $this->root();
        }
        if (null === $node_type || null === $node) {
            return null;
        }
        if (self::KPNODE === $node_type) {
            foreach ($node as $k => $v) {
                $node[$k] = [$v];
            }
        }
        $stack = [[$node_type, $node]];
        do {
            list($node_type, $node) = array_pop($stack);
            if (self::KPNODE === $node_type) {
                $pushed = false;
                foreach ($node as $i) {
                    if (is_array($i)) {
                        list($child_type, $child) = $this->node($i[0]);
                        if (null === $child_type || null === $child) {
                            return null;
                        }
                        if (self::KPNODE === $child_type) {
                            foreach ($child as $k => $v) {
                                $child[$k] = [$v];
                            }
                        }
                        array_push($stack, [$node_type, $node]);
                        array_push($stack, [$child_type, $child]);
                        $pushed = true;

                        break;
                    }
                }
                if ($pushed) {
                    continue;
                }
            }
            if (!empty($stack)) {
                list($upnode_type, $upnode) = array_pop($stack);
            } else {
                list($upnode_type, $upnode) = [null, []];
            }
            $serialized = self::serialize($node_type, $node);
            $to_write = pack('N', strlen($serialized)).$serialized;
            if (($p = $to->tell()) === -1) {
                return null;
            }
            if ($to->write($to_write, strlen($to_write)) !== strlen($to_write)) {
                return null;
            }
            $upnode[current(array_keys($node))] = $p;
            if (!(empty($stack) && null === $upnode_type)) {
                array_push($stack, [$upnode_type, $upnode]);
            }
        } while (!empty($stack));

        return $p;
    }

    /**
     * Get root node.
     *
     * @return array<mixed> 0 => node type, 1 => node; [null, null] on failure
     */
    private function root(): array
    {
        if (($p = $this->roothunt()) === null) {
            return [null, null];
        }

        return $this->node($p);
    }

    /**
     * Try to get position of root.
     *
     * @return int pointer to root; null on failure
     */
    private function roothunt(): ?int
    {
        // try EOF
        if (-1 === $this->file->seek(-(self::SIZEOF_HEADER + self::SIZEOF_INT), SEEK_END)) {
            return null;
        }
        if (strlen($data = $this->file->read(self::SIZEOF_INT + self::SIZEOF_HEADER)) !== self::SIZEOF_INT + self::SIZEOF_HEADER) {
            return null;
        }
        $root = substr($data, 0, self::SIZEOF_INT);
        // header-hunting
        if (self::HEADER !== substr($data, self::SIZEOF_INT)) {
            $root = null;
            if (($size = $this->file->tell()) === -1) {
                return null;
            }
            for ($i = -1; ($off = $i * 128) + $size > 128; --$i) {
                if (-1 === $this->file->seek($off, SEEK_END)) {
                    return null;
                }
                $data = $this->file->read(256);
                if (($pos = strrpos($data, self::HEADER)) !== false) {
                    if (0 === $pos) {
                        continue;
                    }
                    $root = substr($data, $pos - 4, 4);

                    break;
                }
            }
            if (null === $root) {
                return null;
            }
        }
        // unpack root pointer
        list(, $p) = unpack('N', $root);

        return $p;
    }

    /**
     * Get node.
     *
     * @param int $p Pointer to node (offset in file)
     *
     * @return array<mixed> 0 => node type, 1 => node; [null, null] on failure
     */
    private function node(int $p): array
    {
        if (!isset($this->nodecache[$p])) {
            while (count($this->nodecache) + 1 > self::NODECHACHE_SIZE) {
                array_pop($this->nodecache);
            }
            if (-1 === $this->file->seek($p, SEEK_SET)) {
                return [null, null];
            }
            if (self::SIZEOF_INT !== strlen($data = $this->file->read(self::SIZEOF_INT))) {
                return [null, null];
            }
            list(, $n) = unpack('N', $data);
            if (strlen($node = $this->file->read($n)) !== $n) {
                return [null, null];
            }
            $this->nodecache[$p] = self::unserialize($node);
        }

        return $this->nodecache[$p];
    }

    /**
     * Serialize node.
     *
     * @param string       $type node type
     * @param array<mixed> $node node data
     */
    private static function serialize(string $type, array $node): string
    {
        return $type.serialize($node);
    }

    /**
     * Unserialize node.
     *
     * @param string $str serialized node
     *
     * @return array<mixed>
     */
    private static function unserialize(string $str): array
    {
        return [substr($str, 0, 2), unserialize(substr($str, 2))];
    }

    /**
     * Writes header to file.
     *
     * @param File $file The file to write the header ro
     * @param int  $root root position
     *
     * @return bool
     */
    private static function header(File $file, $root)
    {
        $to_write = pack('N', $root).self::HEADER;

        return $file->write($to_write, strlen($to_write)) === strlen($to_write);
    }
}
