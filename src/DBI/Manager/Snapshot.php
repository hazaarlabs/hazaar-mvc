<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\Model;

class Snapshot extends Model
{
    public string $description;
    private Schema $schema;

    public static function create(string $comment): self
    {
        return new Snapshot([
            'description' => $comment,
        ]);
    }

    public function initialise(Adapter $dbi): bool
    {
        $this->schema = Schema::import($dbi);

        return true;
    }

    public function compare(Schema $compareSchema): Migration
    {
        if (!isset($this->schema)) {
            throw new \Exception('Snapshot has not been initialised!');
        }
        $migration = new Migration();
        foreach ($compareSchema->tables as $table) {
            dump($table);
        }

        return $migration;
    }
}
