<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\Controller\Exception\HeadersSent;
use Hazaar\DBI\Manager\Migration\Action\Extension;
use Hazaar\DBI\Manager\Migration\Action\Raise;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
use Hazaar\DBI\Manager\Migration\Event;
use Hazaar\Model;

class Snapshot extends Model
{
    public Version $version;
    public string $comment;
    public Migration $migration;

    /**
     * Creates a new Snapshot instance with the provided comment and a version number based on the current date and time.
     *
     * @param string $comment the comment to associate with the snapshot
     *
     * @return self a new Snapshot instance
     */
    public static function create(string $comment): self
    {
        return new Snapshot([
            'comment' => $comment,
            'version' => [
                'number' => Version::generateVersionNumber(),
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
        $migration->down = new Event();
        // Look for new or changed extensions
        $this->compareExtensions($migration, $masterSchama, $compareSchema);
        // Look for new or changed tables
        $this->compareTables($migration, $masterSchama, $compareSchema);
        // Look for new or changed constraints
        $this->compareConstraints($migration, $masterSchama, $compareSchema);
        // Look for new or changed indexes
        $this->compareIndexes($migration, $masterSchama, $compareSchema);
        // Look for new or changed functions
        $this->compareFunctions($migration, $masterSchama, $compareSchema);
        // Look for new or changed triggers
        $this->compareTriggers($migration, $masterSchama, $compareSchema);
        // Look for new or changed views
        $this->compareViews($migration, $masterSchama, $compareSchema);

        return $this->migration = $migration;
    }

    /**
     * Sets the schema for the snapshot.
     *
     * This method takes a Schema object and converts it to a migration,
     * which is then stored in the $migration property.
     *
     * @param Schema $schema the schema to be set
     */
    public function setSchema(Schema $schema): void
    {
        $this->migration = $schema->toMigration();
        $this->migration->down = new Event();
        $action = new Raise(['message' => 'This migration is not reversible.']);
        $this->migration->down->add(ActionName::RAISE, ActionType::ERROR, $action);
    }

    /**
     * Counts the number of actions in the migration's "up" phase.
     *
     * @return int the number of actions
     */
    public function count(): int
    {
        return count($this->migration->up->actions);
    }

    /**
     * Saves the current migration snapshot to a JSON file in the specified target directory.
     *
     * @param string $targetDir the directory where the migration snapshot file will be saved
     *
     * @return bool returns true if the file was successfully written, false otherwise
     */
    public function save(string $targetDir): bool
    {
        $migrateFile = $targetDir.DIRECTORY_SEPARATOR
            .$this->version->number.'_'
            .str_replace(' ', '_', $this->comment).'.json';

        return file_put_contents($migrateFile, $this->migration->toJSON(JSON_PRETTY_PRINT)) > 0;
    }

    public function getVersion(): Version
    {
        if (isset($this->migration->down)) {
            $this->version->migrate = $this->migration->down;
        }
        $this->version->comment = $this->comment;

        return $this->version;
    }

    private function compareExtensions(Migration $migration, Schema $masterSchama, Schema $compareSchema): void
    {
        $extensions = array_diff($compareSchema->extensions, $masterSchama->extensions);
        if (empty($extensions)) {
            return;
        }
        $migration->up->add(ActionName::CREATE, ActionType::EXTENSION, new Extension($extensions));
    }

    /**
     * Compares the tables between the master schema and the compare schema, and generates the necessary migration actions.
     *
     * @param Migration $migration     the migration object to which the actions will be added
     * @param Schema    $masterSchama  the master schema to compare against
     * @param Schema    $compareSchema the schema to compare with the master schema
     *
     * @throws \Exception If a table in the compare schema does not have a name.
     *
     * This method performs the following actions:
     * - If a table in the compare schema does not exist in the master schema, a create action is added to the migration.
     * - If a table in the compare schema exists in the master schema but has differences, an alter action is added to the migration.
     * - If a table in the master schema does not exist in the compare schema, a drop action is added to the migration.
     */
    private function compareTables(Migration $migration, Schema $masterSchama, Schema $compareSchema): void
    {
        foreach ($compareSchema->tables as $table) {
            if (!isset($table->name)) {
                throw new \Exception('Table name is required by schema');
            }
            $action = Schema::findActionOrComponent($table->name, $masterSchama->tables);
            // Table does not exist in master schema. Add a create action.
            if (null === $action) {
                $migration->up->add(ActionName::CREATE, ActionType::TABLE, $table);
                $migration->down->add(ActionName::DROP, ActionType::TABLE, $table->spec->name);

                continue;
            }
            // Table exists in master schema. Compare the table schemas.
            $diff = $action->diff($table);
            // Table schema is different. Add an alter action.
            if (null !== $diff) {
                $migration->up->add(ActionName::ALTER, ActionType::TABLE, $diff);
                // $migration->down->add(ActionName::ALTER, ActionType::TABLE, $diff->reverse());
            }
        }
        // Look for tables that have been removed'
        foreach ($masterSchama->tables as $table) {
            $action = Schema::findActionOrComponent($table->name, $compareSchema->tables);
            // Table does not exist in compare schema. Add a drop action.
            if (null === $action) {
                $migration->up->add(ActionName::DROP, ActionType::TABLE, $table);
                $migration->down->add(ActionName::CREATE, ActionType::TABLE, $table);
            }
        }
    }

    /**
     * Compares the constraints between the master schema and the schema to be compared.
     * If a constraint does not exist in the master schema, it adds a create action to the migration.
     * If a constraint exists but is different, it adds an alter action to the migration.
     *
     * @param Migration $migration     the migration object to which actions will be added
     * @param Schema    $masterSchama  the master schema to compare against
     * @param Schema    $compareSchema the schema to be compared
     *
     * @throws \Exception if a table or column for a constraint is not found in the master schema
     */
    private function compareConstraints(Migration $migration, Schema $masterSchama, Schema $compareSchema): void
    {
        foreach ($compareSchema->constraints as $constraint) {
            $action = Schema::findActionOrComponent($constraint->name, $masterSchama->constraints);
            // Constraint does not exist in master schema. Add a create action.
            if (null === $action) {
                $table = Schema::findActionOrComponent($constraint->table, $masterSchama->tables);
                if (null === $table) {
                    $table = Schema::findActionOrComponent($constraint->table, $compareSchema->tables);
                }
                if (null === $table) {
                    throw new \Exception('Table not found for constraint: '.$constraint->table);
                }
                // If this is a primary key constraint, check if the column is already a primary key.
                if ('PRIMARY KEY' === $constraint->type) {
                    $column = Schema::findActionOrComponent($constraint->column, $table->columns);
                    if (null === $column) {
                        throw new \Exception('Column not found for primary key constraint: '.$constraint->column);
                    }
                    // Column is already a primary key. Skip the constraint.
                    if (isset($column->primarykey) && true === $column->primarykey) {
                        continue;
                    }
                }
                $migration->up->add(ActionName::CREATE, ActionType::CONSTRAINT, $constraint);
                $migration->down->add(ActionName::DROP, ActionType::CONSTRAINT, $constraint);

                continue;
            }
            // Constraint exists in master schema. Compare the constraint schemas.
            $diff = $action->diff($constraint);
            // Constraint schema is different. Add an alter action.
            if (null !== $diff) {
                $migration->up->add(ActionName::ALTER, ActionType::CONSTRAINT, $diff);
            }
        }
        // Look for constraints that have been removed.
        foreach ($masterSchama->constraints as $constraint) {
            $action = Schema::findActionOrComponent($constraint->name, $compareSchema->constraints);
            // Constraint does not exist in compare schema. Add a drop action.
            if (null === $action) {
                // Check if the table is being dropped and if so, skip the drop constraint action
                // because the table drop action will take care of it.
                $tableAction = Schema::findActionOrComponent($constraint->table, $migration->up->actions);
                if (null !== $tableAction
                    && !(ActionName::DROP === $tableAction->name
                    && ActionType::TABLE === $tableAction->type)) {
                    $migration->up->add(ActionName::DROP, ActionType::CONSTRAINT, $constraint);
                }
                // We still need to add a down action to create the constraint again
                $migration->down->add(ActionName::CREATE, ActionType::CONSTRAINT, $constraint);
            }
        }
    }

    /**
     * Compares the indexes between the master schema and the schema to be compared,
     * and generates the appropriate migration actions.
     *
     * @param Migration $migration     the migration object to which actions will be added
     * @param Schema    $masterSchama  the master schema containing the current state of the database
     * @param Schema    $compareSchema the schema to be compared against the master schema
     */
    private function compareIndexes(Migration $migration, Schema $masterSchama, Schema $compareSchema): void
    {
        foreach ($compareSchema->indexes as $index) {
            $action = Schema::findActionOrComponent($index->name, $masterSchama->indexes);
            // Index does not exist in master schema. Add a create action.
            if (null === $action) {
                $migration->up->add(ActionName::CREATE, ActionType::INDEX, $index);
                $migration->down->add(ActionName::DROP, ActionType::INDEX, $index);

                continue;
            }
            // Index exists in master schema. Compare the index schemas.
            $diff = $action->diff($index);
            // Index schema is different. Add an alter action.
            if (null !== $diff) {
                $migration->up->add(ActionName::ALTER, ActionType::INDEX, $diff);
            }
        }
        // Look for indexes that have been removed.
        foreach ($masterSchama->indexes as $index) {
            $action = Schema::findActionOrComponent($index->name, $compareSchema->indexes);
            // Index does not exist in compare schema. Add a drop action.
            if (null === $action) {
                // Check if the table is being dropped and if so, skip the drop index action
                // because the table drop action will take care of it.
                $tableAction = Schema::findActionOrComponent($index->table, $migration->up->actions);
                if (null !== $tableAction
                    && !(ActionName::DROP === $tableAction->name
                    && ActionType::TABLE === $tableAction->type)) {
                    $migration->up->add(ActionName::DROP, ActionType::INDEX, $index);
                }
                // We still need to add a down action to create the index again
                $migration->down->add(ActionName::CREATE, ActionType::INDEX, $index);
            }
        }
    }

    private function compareFunctions(Migration $migration, Schema $masterSchama, Schema $compareSchema): void
    {
        foreach ($compareSchema->functions as $function) {
            $action = Schema::findActionOrComponent($function->name, $masterSchama->functions);
            if (null === $action) {
                $migration->up->add(ActionName::CREATE, ActionType::FUNC, $function);
                $migration->down->add(ActionName::DROP, ActionType::FUNC, $function);

                continue;
            }
            $diff = $action->diff($function);
            if (null !== $diff) {
                $migration->up->add(ActionName::ALTER, ActionType::FUNC, $diff);
            }
        }
        // Look for functions that have been removed.
        foreach ($masterSchama->functions as $function) {
            $action = Schema::findActionOrComponent($function->name, $compareSchema->functions);
            if (null === $action) {
                $migration->up->add(ActionName::DROP, ActionType::FUNC, $function);
                $migration->down->add(ActionName::CREATE, ActionType::FUNC, $function);
            }
        }
    }

    private function compareTriggers(Migration $migration, Schema $masterSchama, Schema $compareSchema): void
    {
        foreach ($compareSchema->triggers as $trigger) {
            $action = Schema::findActionOrComponent($trigger->name, $masterSchama->triggers);
            if (null === $action) {
                $migration->up->add(ActionName::CREATE, ActionType::TRIGGER, $trigger);
                $migration->down->add(ActionName::DROP, ActionType::TRIGGER, $trigger);

                continue;
            }
            $diff = $action->diff($trigger);
            if (null !== $diff) {
                $migration->up->add(ActionName::ALTER, ActionType::TRIGGER, $diff);
            }
        }
        // Look for triggers that have been removed.
        foreach ($masterSchama->triggers as $trigger) {
            $action = Schema::findActionOrComponent($trigger->name, $compareSchema->triggers);
            if (null === $action) {
                $migration->up->add(ActionName::DROP, ActionType::TRIGGER, $trigger);
                $migration->down->add(ActionName::CREATE, ActionType::TRIGGER, $trigger);
            }
        }
    }

    private function compareViews(Migration $migration, Schema $masterSchama, Schema $compareSchema): void
    {
        foreach ($compareSchema->views as $view) {
            $action = Schema::findActionOrComponent($view->name, $masterSchama->views);
            if (null === $action) {
                $migration->up->add(ActionName::CREATE, ActionType::VIEW, $view);
                $migration->down->add(ActionName::DROP, ActionType::VIEW, $view);

                continue;
            }
            $diff = $action->diff($view);
            if (null !== $diff) {
                $migration->up->add(ActionName::ALTER, ActionType::VIEW, $diff);
            }
        }
        // Look for views that have been removed.
        foreach ($masterSchama->views as $view) {
            $action = Schema::findActionOrComponent($view->name, $compareSchema->views);
            if (null === $action) {
                $migration->up->add(ActionName::DROP, ActionType::VIEW, $view);
                $migration->down->add(ActionName::CREATE, ActionType::VIEW, $view);
            }
        }
    }
}
