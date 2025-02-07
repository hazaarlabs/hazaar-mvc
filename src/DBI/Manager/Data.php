<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\Model;

class Data extends Model
{
    /**
     * @var array<string>
     */
    public array $appliedVersions = [];

    /**
     * @var array<mixed>
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

    public function run(?\Closure $callback = null): bool
    {
        if (is_callable($callback)) {
            $callback('Data sync is working...');
        }

        return true;
    }
}
