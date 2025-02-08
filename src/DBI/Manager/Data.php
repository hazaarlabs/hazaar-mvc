<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Sync\Item;
use Hazaar\DBI\Manager\Sync\Stats;
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
        $dbi->log('Processing '.count($this->items).' sync items');
        $stats = new Stats();
        foreach ($this->items as $item) {
            $item->run($dbi, $stats);
        }
        $dbi->log('Finished processing sync items');
        $dbi->log(sprintf(
            'Processed %d rows: %d inserted, %d updated, %d deleted, %d skipped',
            $stats->rows,
            $stats->inserts,
            $stats->updates,
            $stats->deletes,
            $stats->unchanged
        ));

        return true;
    }
}
