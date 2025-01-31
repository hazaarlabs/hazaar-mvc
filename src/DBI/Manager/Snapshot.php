<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\Controller\Exception\HeadersSent;
use Hazaar\DBI\Manager\Migration\Action\BaseAction;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
use Hazaar\DBI\Manager\Migration\Event;
use Hazaar\Model;

class Snapshot extends Model
{
    public Version $version;
    public string $comment;
    public Migration $migration;

    public static function create(string $comment): self
    {
        return new Snapshot([
            'comment' => $comment,
            'version' => [
                'number' => date('YmdHis'),
            ],
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
        $migration->up = new Event();
        // Look for new or changed tables
        foreach ($compareSchema->tables as $table) {
            if (!isset($table->name)) {
                throw new \Exception('Table name is required by schema');
            }
            $action = $this->findAction($table->name, $masterSchama->tables);
            // Table does not exist in master schema. Add a create action.
            if (null === $action) {
                $migration->up->add(ActionName::CREATE, ActionType::TABLE, $table);

                continue;
            }
            // Table exists in master schema. Compare the table schemas.
            $diff = $action->diff($table);
            // Table schema is different. Add an alter action.
            if (null !== $diff) {
                $migration->up->add(ActionName::ALTER, ActionType::TABLE, $diff);
            }
        }
        // Look for tables that have been removed'
        foreach ($masterSchama->tables as $table) {
            $action = $this->findAction($table->name, $compareSchema->tables);
            // Table does not exist in compare schema. Add a drop action.
            if (null === $action) {
                $migration->up->add(ActionName::DROP, ActionType::TABLE, $table);
            }
        }

        return $this->migration = $migration;
    }

    public function setSchema(Schema $schema): void
    {
        $this->migration = $schema->toMigration();
    }

    public function count(): int
    {
        return count($this->migration->up->actions);
    }

    public function save(string $targetDir): bool
    {
        $migrateFile = $targetDir.DIRECTORY_SEPARATOR
            .$this->version->number.'_'
            .str_replace(' ', '_', $this->comment).'.json';

        return file_put_contents($migrateFile, $this->migration->toJSON(JSON_PRETTY_PRINT)) > 0;
    }

    /**
     * @param array<BaseAction> $haystack
     */
    private function findAction(string $name, array $haystack): ?BaseAction
    {
        foreach ($haystack as $action) {
            if ($action->name === $name) {
                return $action;
            }
        }

        return null;
    }
}
