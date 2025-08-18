## BTree File Utilities ([`Hazaar\Util\BTree`](/api/class/Hazaar/Util/Btree.html))

The `Hazaar\Util\BTree` class provides a robust, file-based B-Tree implementation for PHP. It allows for efficient storage and retrieval of key-value pairs on disk, making it suitable for large datasets that may not fit into memory.

A key feature of this implementation is its optimization for in-place writes. When a value is updated, the class attempts to write the new data into the same location on disk if the new value's size is the same as the old one. This is particularly efficient for key-value pairs where the data size is consistent, such as dates, timestamps, or fixed-length identifiers. This approach minimizes file fragmentation and reduces the need to run the `compact()` operation, leading to better performance and less disk I/O.

## Getting Started

Here is a basic example of how to use the `BTree` class, demonstrating common operations.

```php
<?php

use Hazaar\Util\BTree;

// 1. Create a new B-Tree instance
$btree = new BTree(__DIR__ . '/test.btree');

// 2. Set and get values
$btree->set('my_key', 'my_value');
$btree->set('another_key', 'another_value');

echo $btree->get('my_key'); // Outputs: my_value

// 3. Iterate over the items
foreach ($btree as $key => $value) {
    echo "{$key}: {$value}\n";
}

// 4. Remove a value
$btree->remove('another_key');
var_dump($btree->get('another_key')); // Outputs: NULL

// 5. The file handle is closed automatically when the object is destroyed.
// Or you can explicitly close it.
$btree->close();

?>
```

---

## Class Reference

### `__construct(string $filePath, bool $readOnly = false, int $keySize = 32, int $slotSize = 16)`

Creates a new `BTree` instance or opens an existing one.

-   **`$filePath`**: The absolute path to the B-Tree file. If the file does not exist, it will be created.
-   **`$readOnly`**: (Optional) If `true`, the file is opened in read-only mode, and any attempts to modify the tree (like `set`, `remove`, `compact`) will fail. Defaults to `false`.
-   **`$keySize`**: (Optional) The maximum size in bytes for each key. All keys will be padded to this length. Defaults to `32`.
-   **`$slotSize`**: (Optional) The number of slots (key/pointer pairs) in each node. Defaults to `16`.

### `set(string $key, mixed $value): bool`

Sets or updates a value for a given key. The value can be any serializable PHP type.

-   Returns `true` on success, `false` on failure (e.g., in read-only mode).

### `get(string $key): mixed`

Retrieves the value associated with a given key.

-   Returns the value if the key exists, or `null` if the key is not found.

### `remove(string $key): bool`

Removes a key and its associated value from the B-Tree. The space occupied by the removed record is marked as free but is not immediately reclaimed.

-   Returns `true` on success.

### `getIterator(): \Generator`

Returns a generator that can be used to iterate over the B-Tree using a `foreach` loop. This is the required method for the `IteratorAggregate` interface.

---

## File Management

### `compact(): bool`

Rebuilds the B-Tree file to remove empty space created by `remove()` operations. This is useful for reducing the file size and improving performance after a large number of deletions.

```php
// After removing many records, the file size may be larger than necessary.
$btree->compact(); 
```

-   Returns `true` on success, `false` on failure.

### `verify(): bool`

Verifies the integrity of the B-Tree structure. This method traverses the tree to ensure all nodes and records are correctly linked and accessible. It's useful for detecting file corruption.

```php
if ($btree->verify()) {
    echo "The B-Tree is valid.";
} else {
    echo "The B-Tree is corrupt!";
}
```

-   Returns `true` if the tree is valid, `false` otherwise.

### `close(): void`

Closes the B-Tree file handle. This method is called automatically by the class destructor, so you typically do not need to call it manually unless you want to close the file before the object goes out of scope.

---

## Utility Methods

### `count(): int`

Returns the total number of key-value pairs currently stored in the B-Tree.

```php
echo "The B-Tree contains " . $btree->count() . " items.";
```

### `empty(): bool`

Removes all key-value pairs from the B-Tree, effectively resetting it to an empty state.

-   Returns `true` on success.

### `toArray(): array`

Returns the entire B-Tree as a PHP associative array.

**Warning**: This can consume a large amount of memory for large B-Trees.

```php
$data = $btree->toArray();
foreach ($data as $key => $value) {
    // ...
}
```

## Advanced Example: Bulk Operations

The following example demonstrates a more complex usage pattern: populating the tree with many records, removing some of them, closing and reopening the file, and then compacting it.

```php
<?php

require 'vendor/autoload.php';
use Hazaar\Util\BTree;

$file = __DIR__ . '/test.btree';
if (file_exists($file)) unlink($file);

$keySize = 32;
$btree = new BTree($file, false, $keySize);

// Insert 1000 records
$keyIndex = [];
for ($i = 0; $i < 1000; ++$i) {
    $key = uniqid('key_');
    $value = "value_for_{$key}";
    $keyIndex[$key] = $value;
    $btree->set($key, $value);
}

// Remove half of the records
$removedKeys = [];
$i = 0;
foreach ($keyIndex as $key => $value) {
    if ($i++ % 2 === 0) {
        $btree->remove($key);
        $removedKeys[] = $key;
        unset($keyIndex[$key]);
    }
}

echo "Items before close: " . $btree->count() . "\n"; // Should be ~500

// Close and reopen the B-Tree
$btree->close();
$btree = new BTree($file, false, $keySize);

echo "Items after reopen: " . $btree->count() . "\n"; // Should be the same

// Verify that a remaining key still exists
$firstKey = key($keyIndex);
echo "Value for {$firstKey}: " . $btree->get($firstKey) . "\n";

// Verify that a removed key is gone
$firstRemovedKey = $removedKeys[0];
var_dump($btree->get($firstRemovedKey)); // NULL

// Compact the file to reclaim space
echo "Compacting file...\n";
$btree->compact();

// Verify the tree's integrity after compaction
if ($btree->verify()) {
    echo "Tree is still valid after compaction.\n";
    echo "Items after compact: " . $btree->count() . "\n"; // Should be the same
}

$btree->close();
?>
```
