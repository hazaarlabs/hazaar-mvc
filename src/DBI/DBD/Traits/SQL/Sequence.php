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
        $sql = $this->queryBuilder->select('*')
            ->from('information_schema.sequences')
            ->where(['sequence_name' => $name, 'sequence_schema' => $this->queryBuilder->getSchemaName()])
            ->toString()
        ;
        $result = $this->query($sql);
        if (false === $result) {
            return false;
        }
        $sequence_info = $result->fetch(\PDO::FETCH_ASSOC);

        return [
            'name' => $sequence_info['sequence_name'],
            'type' => $sequence_info['data_type'],
            'increment' => (int) $sequence_info['increment'],
            'min' => (int) $sequence_info['minimum_value'],
            'max' => (int) $sequence_info['maximum_value'],
            'start' => (int) $sequence_info['start_value'],
        ];
    }

    /**
     * @param array{
     * type: string,
     * increment: int,
     * min: int,
     * max: int,
     * start: int,
     * } $sequence_info
     */
    public function createSequence(string $name, array $sequence_info, bool $ifNotExists = false): bool
    {
        $sql = 'CREATE SEQUENCE'.($ifNotExists ? ' IF NOT EXISTS ' : ' ').$this->queryBuilder->field($name);
        if (array_key_exists('type', $sequence_info)) {
            $sql .= ' AS '.$sequence_info['type'];
        }
        if (array_key_exists('increment', $sequence_info)) {
            $sql .= ' INCREMENT BY '.$sequence_info['increment'];
        }
        if (array_key_exists('min', $sequence_info)) {
            $sql .= ' MINVALUE '.$sequence_info['min'];
        }
        if (array_key_exists('max', $sequence_info)) {
            $sql .= ' MAXVALUE '.$sequence_info['max'];
        }
        if (array_key_exists('start', $sequence_info)) {
            $sql .= ' START WITH '.$sequence_info['start'];
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
