<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait StoredFunction
{
    /**
     * List defined functions.
     *
     * @return array<int,array<mixed>|string>
     */
    public function listFunctions(bool $includeParameters = false): array
    {
        $schemaName = $this->queryBuilder->getSchemaName();
        $sql = "SELECT r.specific_name, 
                r.routine_schema, 
                r.routine_name, 
                p.data_type 
            FROM INFORMATION_SCHEMA.routines r 
            LEFT JOIN INFORMATION_SCHEMA.parameters p ON p.specific_name=r.specific_name
            WHERE r.routine_type='FUNCTION'
            AND r.specific_schema=".$this->queryBuilder->prepareValue($schemaName)."
            AND NOT (r.routine_body='EXTERNAL' AND r.external_language='C')
            ORDER BY r.routine_name, p.ordinal_position;";
        $q = $this->query($sql);
        $list = [];
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            $id = $includeParameters ? $row['specific_name'] : $row['routine_schema'].$row['routine_name'];
            if (true !== $includeParameters) {
                if (!array_key_exists($id, $list)) {
                    $list[$id] = $row['routine_name'];
                }

                continue;
            }
            if (!array_key_exists($id, $list)) {
                $list[$id] = [
                    'name' => $row['routine_name'],
                    'parameters' => [],
                ];
            }
            if ($row['data_type']) {
                $list[$id]['parameters'][] = $row['data_type'];
            }
        }

        return array_values($list);
    }

    public function functionExists(string $functionName, ?string $argTypes = null): bool
    {
        return in_array($functionName, $this->listFunctions());
    }

    /**
     * @return array<int,array{
     *  name:string,
     *  return_type:string,
     *  body:string,
     *  parameters:?array<int,array{name:string,type:string,mode:string,ordinal_position:int}>,
     *  lang:string
     * }>|false
     */
    public function describeFunction(string $name, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->queryBuilder->getSchemaName();
        }
        $sql = "SELECT r.specific_name,
                    r.routine_schema,
                    r.routine_name,
                    r.data_type AS return_type,
                    r.routine_body,
                    r.routine_definition,
                    r.external_language,
                    p.parameter_name,
                    p.data_type,
                    p.parameter_mode,
                    p.ordinal_position
                FROM INFORMATION_SCHEMA.routines r
                LEFT JOIN INFORMATION_SCHEMA.parameters p ON p.specific_name=r.specific_name
                WHERE r.routine_type='FUNCTION'
                AND r.routine_schema=".$this->queryBuilder->prepareValue($schemaName).'
                AND r.routine_name='.$this->queryBuilder->prepareValue($name).'
                ORDER BY r.routine_name, p.ordinal_position;';
        if (!($q = $this->query($sql))) {
            throw new \Exception($this->errorInfo()[2]);
        }
        $info = [];
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            if (!array_key_exists($row['specific_name'], $info)) {
                if (!($routineDefinition = ake($row, 'routine_definition'))) {
                    continue;
                }
                $item = [
                    'name' => $row['routine_name'],
                    'return_type' => $row['return_type'],
                    'body' => trim($routineDefinition),
                ];
                $item['parameters'] = [];
                $item['lang'] = ('EXTERNAL' === strtoupper($row['routine_body']))
                    ? $row['external_language']
                    : $row['routine_body'];
                $info[$row['specific_name']] = $item;
            }
            if (null === $row['parameter_name']) {
                continue;
            }
            $info[$row['specific_name']]['parameters'][] = [
                'name' => $row['parameter_name'],
                'type' => $row['data_type'],
                'mode' => $row['parameter_mode'],
                'ordinal_position' => $row['ordinal_position'],
            ];
        }
        usort($info, function ($a, $b) {
            if (count($a['parameters']) === count($b['parameters'])) {
                return 0;
            }

            return count($a['parameters']) < count($b['parameters']) ? -1 : 1;
        });

        return $info;
    }

    /**
     * Create a new database function.
     *
     * @param mixed $name The name of the function to create
     * @param mixed $spec A function specification.  This is basically the array returned from describeFunction()
     */
    public function createFunction($name, $spec): bool
    {
        $sql = 'CREATE OR REPLACE FUNCTION '.$this->queryBuilder->schemaName($name).' (';
        if ($params = ake($spec, 'parameters')) {
            $items = [];
            foreach ($params as $param) {
                $items[] = ake($param, 'mode', 'IN').' '.ake($param, 'name').' '.ake($param, 'type');
            }
            $sql .= implode(', ', $items);
        }
        $sql .= ') RETURNS '.ake($spec, 'return_type', 'TEXT').' LANGUAGE '.ake($spec, 'lang', 'SQL')." AS\n\$BODY$ ";
        $sql .= ake($spec, 'body');
        $sql .= '$BODY$;';

        return false !== $this->exec($sql);
    }

    /**
     * Remove a function from the database.
     *
     * @param string                    $name     The name of the function to remove
     * @param null|array<string>|string $argTypes the argument list of the function to remove
     * @param bool                      $cascade  Whether to perform a DROP CASCADE
     */
    public function dropFunction(
        string $name,
        null|array|string $argTypes = null,
        bool $cascade = false,
        bool $ifExists = false
    ): bool {
        $sql = 'DROP FUNCTION ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->schemaName($name);
        if (null !== $argTypes) {
            $sql .= '('.(is_array($argTypes) ? implode(', ', $argTypes) : $argTypes).')';
        }
        if (true === $cascade) {
            $sql .= ' CASCADE';
        }

        return false !== $this->exec($sql);
    }
}
