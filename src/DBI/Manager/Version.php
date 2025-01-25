<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
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
    public string $description;

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
            .' '.$this->description;
    }

    /**
     * Loads a schema version from a file.
     *
     * This method reads the specified file and extracts version information
     * from its filename. The filename must follow the pattern: {number}_{description}.json
     * where {number} is a sequence of digits and {description} is a word.
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
            'description' => str_replace('_', ' ', $matches[2]),
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
                throw new \Exception('Failed to apply version '.$this->number);
            }
            $this->migrate = $migration->down;
            $dbi->table('schema_version')->insert($this);
        } catch (\Exception $e) {
            $dbi->cancel();

            throw $e;
        }
        $dbi->commit();

        return true;
    }

    private function loadMigrationEventFunctions(Event $event): void
    {
        foreach ($event->actions as $action) {
            if (match ($action->type) {
                ActionType::FUNC,
                ActionType::TRIGGER => false,
                default => true,
            } || isset($action->spec->content)) {
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
