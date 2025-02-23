<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait Sequence
{
    use Schema;

    /**
     * @return array<string>
     */
    public function listSequences(): array
    {
        return array_column($this->listInformationSchema('sequences', ['sequence_name'], [
            'sequence_schema' => $this->queryBuilder->getSchemaName(),
        ]), 'sequence_name');
    }

    public function sequenceExists(string $sequenceName): bool
    {
        return in_array($sequenceName, $this->listSequences());
    }

    /**
     * @return array{
     *  name: string,
     *  type: string,
     *  increment: int,
     *  min: int,
     *  max: int,
     *  start: int,
     * }|false
     */
    public function describeSequence(string $name): array|false
    {
        $sql = $this->queryBuilder->reset()->select('*')
            ->from('information_schema.sequences')
            ->where(['sequence_name' => $name, 'sequence_schema' => $this->queryBuilder->getSchemaName()])
            ->toString()
        ;
        $result = $this->query($sql);
        if (false === $result) {
            return false;
        }
        $sequenceInfo = $result->fetch(\PDO::FETCH_ASSOC);

        return [
            'name' => $sequenceInfo['sequence_name'],
            'type' => $sequenceInfo['data_type'],
            'increment' => (int) $sequenceInfo['increment'],
            'min' => (int) $sequenceInfo['minimum_value'],
            'max' => (int) $sequenceInfo['maximum_value'],
            'start' => (int) $sequenceInfo['start_value'],
        ];
    }

    /**
     * @param array<mixed> $sequenceInfo
     */
    public function createSequence(string $name, array $sequenceInfo, bool $ifNotExists = false): bool
    {
        $sql = 'CREATE SEQUENCE'.($ifNotExists ? ' IF NOT EXISTS ' : ' ').$this->queryBuilder->field($name);
        if (array_key_exists('type', $sequenceInfo)) {
            $sql .= ' AS '.$sequenceInfo['type'];
        }
        if (array_key_exists('increment', $sequenceInfo)) {
            $sql .= ' INCREMENT BY '.$sequenceInfo['increment'];
        }
        if (array_key_exists('min', $sequenceInfo)) {
            $sql .= ' MINVALUE '.$sequenceInfo['min'];
        }
        if (array_key_exists('max', $sequenceInfo)) {
            $sql .= ' MAXVALUE '.$sequenceInfo['max'];
        }
        if (array_key_exists('start', $sequenceInfo)) {
            $sql .= ' START WITH '.$sequenceInfo['start'];
        }

        return false !== $this->exec($sql);
    }

    public function dropSequence(string $name, bool $ifExists = false): bool
    {
        $sql = 'DROP SEQUENCE '.($ifExists ? 'IF EXISTS ' : '').$this->queryBuilder->field($name);

        return false !== $this->exec($sql);
    }

    public function nextSequenceValue(string $name): false|int
    {
        $sql = 'SELECT NEXTVAL('.$this->queryBuilder->field($name).')';
        $result = $this->query($sql);
        if (false === $result) {
            return false;
        }

        return (int) $result->fetchColumn();
    }

    public function setSequenceValue(string $name, int $value): bool
    {
        $sql = 'SELECT SETVAL('.$this->queryBuilder->field($name).', '.$value.')';

        return false !== $this->exec($sql);
    }
}
