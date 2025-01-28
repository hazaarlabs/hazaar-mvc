<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\Controller\Exception\HeadersSent;
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

    /**
     * Compare a master schema to a compare schema and generate a migration.
     *
     * This method will compare the master schema to the compare schema and generate a migration that can be used to update the master schema to match the compare schema.
     *
     * @return null|Migration Returns a migration object that can be used to update the master schema to match the compare schema. If the schemas are identical, null will be returned.
     *
     * @throws \Exception
     * @throws HeadersSent
     */
    public function compare(Schema $masterSchama, Schema $compareSchema): ?Migration
    {
        $migration = new Migration();
        foreach ($compareSchema->tables as $table) {
            dump($table);
        }

        return $migration;
    }
}
