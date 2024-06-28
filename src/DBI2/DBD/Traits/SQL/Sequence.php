<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Sequence
{
    use Schema;

    /**
     * @return array<string>
     */
    public function listSequences(): array
    {
        return $this->listInformationSchema('sequences', ['sequence_name'], [
            'sequence_schema' => $this->queryBuilder->getSchemaName(),
        ]);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeSequence(string $name): array|false
    {
        $sql = $this->queryBuilder->select('*')
            ->from('information_schema.sequences')
            ->where(['sequence_name' => $name, 'sequence_schema' => $this->queryBuilder->getSchemaName()])
            ->toString()
        ;
        $result = $this->query($sql);

        return $result->fetch(\PDO::FETCH_ASSOC);
    }

    public function createSequence(string $name, int $start = 1, int $increment = 1): bool
    {
        return false;
    }

    public function dropSequence(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    public function nextSequenceValue(string $name): false|int
    {
        return false;
    }

    public function setSequenceValue(string $name, int $value): bool
    {
        return false;
    }
}
