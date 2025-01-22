<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface;

use Hazaar\DBI\Row;

interface Result
{
    public function toString(): string;

    /**
     * @return array<mixed>|false
     */
    public function fetch(
        int $fetchStyle = \PDO::FETCH_ASSOC,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): array|false;

    /**
     * @return array<mixed>|false
     */
    public function fetchAll(
        int $fetchMode = \PDO::FETCH_ASSOC,
        mixed $fetchArgument = null
    ): array|false;

    public function fetchColumn(int $columnNumber = 0): mixed;

    /**
     * @param array<mixed> $constructorArgs
     */
    public function fetchObject(string $className = 'stdClass', array $constructorArgs = []): false|object;

    public function rowCount(): int;

    public function columnCount(): int;

    public function row(int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $offset = 0): ?Row;
}
