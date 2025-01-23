<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Schema;

use Hazaar\Model;
use Hazaar\Model\Attribute\Required;

class Version extends Model
{
    /**
     * @var array{dirname: string, basename: string, extension: string, filename: string}
     */
    public array $sourceFile;
    public string $description;
    public bool $valid = true;
    #[Required]
    protected string $number;

    public function __toString()
    {
        return str_pad($this->number, 10, '0', STR_PAD_RIGHT).' '
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
        $sourceFile = pathinfo($filename);
        $matches = [];
        if (!(isset($sourceFile['extension'])
            && 'json' === $sourceFile['extension']
            && preg_match('/^(\d+)_(\w+)$/', $sourceFile['filename'], $matches))) {
            return null;
        }

        return new self([
            'sourceFile' => $sourceFile,
            'number' => (int) str_pad($matches[1], 14, '0', STR_PAD_RIGHT),
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
        return $this->sourceFile['dirname'].DIRECTORY_SEPARATOR.$this->sourceFile['basename'];
    }

    /**
     * Retrieves the migration script from the source file.
     *
     * This method reads the content of the source file, decodes it from JSON format,
     * and returns it as a \stdClass object. If the JSON content is invalid, an exception
     * is thrown.
     *
     * @return array<mixed> the decoded JSON content from the source file
     *
     * @throws \Exception if the JSON content is invalid
     */
    public function getMigrationScript(): array
    {
        $rawContent = file_get_contents($this->sourceFile['dirname'].DIRECTORY_SEPARATOR.$this->sourceFile['basename']);
        $content = json_decode($rawContent, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception('Error reading schema migration file: '.$this->getSourceFile());
        }

        return $content;
    }

    public function replay(): void
    {
        throw new \Exception('Not implemented');
    }
}
