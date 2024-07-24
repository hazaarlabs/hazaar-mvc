<?php

namespace Hazaar\DBI\DBD\Interfaces\SQL;

interface Sequence
{
    /**
     * @return array<string>
     */
    public function listSequences(): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function describeSequence(string $name): array|false;

    public function createSequence(string $name, int $start = 1, int $increment = 1): bool;

    public function dropSequence(string $name, bool $ifExists = false): bool;

    public function nextSequenceValue(string $name): false|int;

    public function setSequenceValue(string $name, int $value): bool;
}
