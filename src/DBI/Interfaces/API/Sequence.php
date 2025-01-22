<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interfaces\API;

interface Sequence
{
    /**
     * List all sequences in the database.
     *
     * @return array<mixed>
     */
    public function listSequences(): array;

    /**
     * Check if a sequence exists.
     *
     * @param string $sequenceName The name of the sequence to check
     */
    public function sequenceExists(string $sequenceName): bool;

    /**
     * Describe a sequence.
     *
     * @param string $name The name of the sequence to describe
     *
     * @return array<mixed>|false
     */
    public function describeSequence(string $name): array|false;

    /**
     * Create a new sequence.
     *
     * @param string       $name         The name of the sequence to create
     * @param array<mixed> $sequenceInfo
     */
    public function createSequence(string $name, array $sequenceInfo, bool $ifNotExists = false): bool;

    /**
     * Drop a sequence.
     *
     * @param string $name     The name of the sequence to drop
     * @param bool   $ifExists If true, the sequence will only be dropped if it exists
     */
    public function dropSequence(string $name, bool $ifExists = false): bool;

    /**
     * Get the next value from a sequence.
     *
     * @param string $name The name of the sequence to get the next value from
     */
    public function nextSequenceValue(string $name): false|int;

    /**
     * Set the value of a sequence.
     *
     * @param string $name  The name of the sequence to set the value of
     * @param int    $value The value to set the sequence to
     */
    public function setSequenceValue(string $name, int $value): bool;
}
