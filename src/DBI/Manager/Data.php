<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Sync\Item;
use Hazaar\Model;

class Data extends Model
{
    /**
     * @var array<string>
     */
    public array $appliedVersions = [];

    /**
     * @var array<Item>
     */
    public array $items = [];

    public function construct(array &$data): void
    {
        $data = ['items' => $data];
    }

    public static function load(string $sourceFile): self
    {
        if (!file_exists($sourceFile)) {
            throw new \Exception('Data file '.$sourceFile.' does not exist');
        }

        $data = json_decode(file_get_contents($sourceFile), true);
        if (null === $data) {
            throw new \Exception('Failed to load data from '.$sourceFile);
        }

        return new self($data);
    }

    public function getHash(): string
    {
        return md5(json_encode($this->items));
    }

    public function run(Adapter $dbi): bool
    {
        $dbi->log('Data sync is working...');
        foreach ($this->items as $item) {
            if (isset($item->message)) {
                $dbi->log($item->message);
            }
            if (!isset($item->table)) {
                continue;
            }
            $dbi->log("Syncing rows to table '{$item->table}'");
            // TODO: Add the bit that does the actual syncing
        }
        $dbi->log('Data sync is complete');

        return true;
    }
}
