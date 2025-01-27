<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\Model;

class Snapshot extends Model
{
    public string $description;

    public static function create(string $comment): self
    {
        return new Snapshot([
            'description' => $comment,
        ]);
    }

    public function initialise(Adapter $dbi): bool
    {
        return false;
    }

    public function compare(Schema $compareSchema): Migration
    {
        return new Migration();
    }
}
