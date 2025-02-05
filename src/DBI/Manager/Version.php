<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
use Hazaar\DBI\Manager\Migration\Event;
use Hazaar\Model;
use Hazaar\Model\Attribute\Hide;
use Hazaar\Model\Attribute\Json;
use Hazaar\Model\Attribute\MaxLength;
use Hazaar\Model\Attribute\MinLength;
use Hazaar\Model\Attribute\Required;

class Version extends Model
{
    public string $comment;

    /**
     * @var array{dirname: string, basename: string, extension: string, filename: string}
     */
    #[Hide('dbiWrite')]
    protected array $source;
    #[Required]
    #[MinLength(14)]
    #[MaxLength(14)]
    protected int $number;
    #[Hide('dbiWrite')]
    protected bool $valid = true;
    protected Event $migrate;

    public function __toString()
    {
        return sprintf('%-10s', $this->number).' '
            .($this->valid ? "\u{2713}" : "\u{2717}")
            .' '.$this->comment;
    }

    /**
     * Loads a schema version from a file.
     *
     * This method reads the specified file and extracts version information
     * from its filename. The filename must follow the pattern: {number}_{comment}.json
     * where {number} is a sequence of digits and {comment} is a word.
     *
     * @param string $filename the path to the file to load
     *
     * @return null|self returns an instance of self if the file is valid, otherwise null
     */
    public static function loadFromFile(string $filename): ?self
    {
        $source = pathinfo($filename);
        $matches = [];
        if (!(isset($source['extension'])
            && 'json' === $source['extension']
            && preg_match('/^(\d+)_(\w+)$/', $source['filename'], $matches))) {
            return null;
        }

        return new self([
            'source' => $source,
            'number' => str_pad($matches[1], 14, '0', STR_PAD_RIGHT),
            'comment' => str_replace('_', ' ', $matches[2]),
        ]);
    }

    /**
     * Retrieves the full path of the source file.
     *
     * This method constructs the full path of the source file by concatenating
     * the directory name and the base name of the source file, separated by
     * the directory separator.
     *
     * @return string the full path of the source file
     */
    public function getSourceFile(): string
    {
        return $this->source['dirname'].DIRECTORY_SEPARATOR.$this->source['basename'];
    }

    /**
     * Retrieves the migration script from the source file.
     *
     * This method reads the content of the source file, decodes it from JSON format,
     * and returns it as a \stdClass object. If the JSON content is invalid, an exception
     * is thrown.
     *
     * @return null|Migration The migration script object
     *
     * @throws \Exception if the JSON content is invalid
     */
    public function loadMigration(): ?Migration
    {
        if (!isset($this->source)) {
            return null;
        }
        $rawContent = file_get_contents($this->source['dirname'].DIRECTORY_SEPARATOR.$this->source['basename']);
        $content = json_decode($rawContent);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception('Error reading schema migration file: '.$this->getSourceFile());
        }
        $migration = new Migration($content);
        if (isset($migration->up)) {
            $this->loadMigrationEventFunctions($migration->up);
        }
        if (isset($migration->down)) {
            $this->loadMigrationEventFunctions($migration->down);
        }

        return $migration;
    }

    /**
     * Replays the migration for the current version.
     *
     * This method loads the migration, begins a database transaction, and attempts to replay the migration.
     * If the migration is successfully replayed, it updates the schema version in the database.
     * If any exception occurs during the process, the transaction is canceled and the exception is rethrown.
     *
     * @param Adapter $dbi the database adapter instance
     *
     * @return bool returns true if the migration was successfully replayed, false otherwise
     *
     * @throws \Exception if the migration replay fails or any other error occurs during the process
     */
    public function replay(Adapter $dbi): bool
    {
        $migration = $this->loadMigration();
        if (null === $migration) {
            return false;
        }

        try {
            $dbi->begin();
            $result = $migration->replay($dbi);
            if (false === $result) {
                throw new \Exception('Failed to apply version '.$this->number.'. Last error was: '.$dbi->errorInfo()[2]);
            }
            if (isset($migration->down)) {
                $this->migrate = $migration->down;
            }
            $dbi->table('schema_version')->insert($this);
        } catch (\Exception $e) {
            $dbi->cancel();

            throw $e;
        }
        $dbi->commit();

        return true;
    }

    /**
     * Rollbacks the current migration version.
     *
     * This method attempts to rollback the current migration version by running the
     * `down` method of the migration. If the rollback is successful, the version
     * number is removed from the `schema_version` table. If the rollback fails, an
     * exception is thrown and the transaction is cancelled.
     *
     * @param Adapter $dbi the database adapter instance
     *
     * @return bool returns true if the rollback is successful
     *
     * @throws \Exception if the rollback fails
     */
    public function rollback(Adapter $dbi): bool
    {
        if (!isset($this->migrate)) {
            $migration = $this->loadMigration();
            if (null === $migration || !isset($migration->down)) {
                throw new \Exception('No down migration found for version '.$this->number);
            }
            $this->migrate = $migration->down;
        }

        try {
            $dbi->begin();
            $result = $this->migrate->run($dbi);
            if (false === $result) {
                throw new \Exception('Failed to rollback version '.$this->number);
            }
            $dbi->table('schema_version')->delete(['number' => $this->number]);
        } catch (\Exception $e) {
            $dbi->cancel();

            throw $e;
        }
        $dbi->commit();

        return true;
    }

    /**
     * Unlinks (deletes) the source file associated with this object.
     *
     * @return bool returns true on success or false on failure
     */
    public function unlink(): bool
    {
        return unlink($this->getSourceFile());
    }

    protected function constructed(): void
    {
        $this->defineEventHook('serialize', function () {
            if (!isset($this->migrate)) {
                $migration = $this->loadMigration();
                if (null !== $migration) {
                    $this->migrate = $migration->down;
                }
            }
        });
    }

    /**
     * Loads migration event functions from the specified event.
     *
     * This method iterates through the actions of the given event and checks if the action type
     * is either a function or a trigger. If the action type is not a function or trigger, or if
     * the action specification content is already set, it continues to the next action.
     *
     * For each action that requires loading content, it constructs the file path to the SQL file
     * containing the function definition. If the file exists, it reads the content of the file
     * and assigns it to the action specification content.
     *
     * @param Event $event the event containing actions to be processed
     */
    private function loadMigrationEventFunctions(Event $event): void
    {
        foreach ($event->actions as $action) {
            if (ActionName::DROP === $action->name
                || match ($action->type) {
                    ActionType::FUNC,
                    ActionType::TRIGGER => false,
                    default => true,
                } || true === isset($action->spec->content)) {
                continue;
            }
            $contentFile = $this->source['dirname']
                .DIRECTORY_SEPARATOR.'functions'
                .DIRECTORY_SEPARATOR.$this->number
                .DIRECTORY_SEPARATOR.$action->spec->name.'.sql';
            if (!file_exists($contentFile)) {
                continue;
            }
            $action->spec->content = file_get_contents($contentFile);
        }
    }
}
