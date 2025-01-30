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
        $migration->up = new Event();
        // Look for new or changed tables
        foreach ($compareSchema->tables as $table) {
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
        // dump($migration->up->toArray());

        return $migration;
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
