<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait Constraint
{
    use Schema;

    /**
     * Retrieves a list of constraints for a given table or all tables in the schema.
     *
     * @param null|string $table      The name of the table. If null, constraints for all tables in the schema will be retrieved.
     * @param null|string $type       The type of constraints to retrieve. If null, all types of constraints will be retrieved.
     * @param bool        $invertType Whether to invert the constraint type filter. If true, constraints of types other than the specified type will be retrieved.
     *
     * @return array<array{
     *  table:string,
     *  column:string,
     *  type:string,
     *  match_option:?string,
     *  update_rule:?string,
     *  delete_rule:?string,
     *  references:?array{table:string,column:string}
     * }>|false an array of constraints or false if constraints are not allowed or an error occurred
     */
    public function listConstraints($table = null, $type = null, $invertType = false): array|false
    {
        $queryBuilder = $this->getQueryBuilder();
        if ($table) {
            [$schema, $table] = $queryBuilder->parseSchemaName($table);
        } else {
            $schema = $queryBuilder->getSchemaName();
        }
        $constraints = [];
        $sql = 'SELECT
                tc.constraint_name as name,
                tc.table_name as '.$queryBuilder->field('table').',
                tc.table_schema as '.$queryBuilder->field('schema').',
                kcu.column_name as '.$queryBuilder->field('column').",
                ccu.table_schema AS foreign_schema,
                ccu.table_name AS foreign_table,
                ccu.column_name AS foreign_column,
                tc.constraint_type as type,
                rc.match_option,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_schema = tc.constraint_schema
                AND kcu.constraint_name = tc.constraint_name
                AND kcu.table_schema = tc.table_schema
                AND kcu.table_name = tc.table_name
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
            LEFT JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
            WHERE tc.CONSTRAINT_SCHEMA='{$schema}'";
        if ($table) {
            $sql .= "\nAND tc.table_name='{$table}'";
        }
        if ($type) {
            $sql .= "\nAND tc.constraint_type".($invertType ? '!=' : '=')."'{$type}'";
        }
        $sql .= ';';
        if ($result = $this->query($sql)) {
            while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                if ($constraint = $constraints[$row['name']] ?? null) {
                    if (!is_array($constraint['column'])) {
                        $constraint['column'] = [$constraint['column']];
                    }
                    if (!in_array($row['column'], $constraint['column'])) {
                        $constraint['column'][] = $row['column'];
                    }
                } else {
                    $constraint = [
                        'name' => $row['name'],
                        'table' => $row['table'],
                        'column' => $row['column'],
                        'type' => $row['type'],
                    ];
                }
                foreach (['match_option', 'update_rule', 'delete_rule'] as $rule) {
                    if ($row[$rule]) {
                        $constraint[$rule] = $row[$rule];
                    }
                }
                if ('FOREIGN KEY' == $row['type'] && $row['foreign_table']) {
                    if (isset($constraint['references'])) {
                        if (!in_array($row['foreign_column'], $constraint['references']['column'])) {
                            $constraint['references']['column'][] = $row['foreign_column'];
                        }
                    } else {
                        $constraint['references'] = [
                            'table' => $row['foreign_table'],
                            'column' => $row['foreign_column'],
                        ];
                    }
                }
                $constraints[$row['name']] = $constraint;
            }

            return $constraints;
        }

        return false;
    }

    /**
     * Adds a constraint to a table in the database.
     *
     * @param string       $constraintName the name of the constraint to add
     * @param array<mixed> $info           An associative array containing information about the constraint.
     *                                     - 'table' (string): The name of the table to which the constraint will be added.
     *                                     - 'type' (string): The type of the constraint (e.g., 'FOREIGN KEY'). If not provided and 'references' is present, defaults to 'FOREIGN KEY'.
     *                                     - 'column' (string|array): The column or columns to which the constraint applies.
     *                                     - 'references' (array): An associative array containing information about the referenced table and column (for foreign key constraints).
     *                                     - 'table' (string): The name of the referenced table.
     *                                     - 'column' (string): The name of the referenced column.
     *                                     - 'update_rule' (string): The action to take on update (for foreign key constraints). Defaults to 'NO ACTION'.
     *                                     - 'delete_rule' (string): The action to take on delete (for foreign key constraints). Defaults to 'NO ACTION'.
     *
     * @return bool returns true on success, or false on failure
     *
     * @throws \Exception if required information is missing or an error occurs while creating the constraint
     */
    public function addConstraint(string $constraintName, array $info): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        if (!array_key_exists('table', $info)) {
            throw new \Exception("Create constraint '{$info['name']}' failed.  Missing table.");
        }
        if (!array_key_exists('type', $info) || !$info['type']) {
            if (array_key_exists('references', $info)) {
                $info['type'] = 'FOREIGN KEY';
            } else {
                throw new \Exception("Create constraint '{$info['name']}' failed.  Missing constraint type.");
            }
        }
        if ('FOREIGN KEY' == strtoupper($info['type'])) {
            if (!array_key_exists('update_rule', $info)) {
                $info['update_rule'] = 'NO ACTION';
            }
            if (!array_key_exists('delete_rule', $info)) {
                $info['delete_rule'] = 'NO ACTION';
            }
        }
        $column = $info['column'] ?? null;
        if (is_array($column)) {
            foreach ($column as &$col) {
                $col = $queryBuilder->field($col);
            }
            $column = implode(', ', $column);
        } elseif ($column) {
            $column = $queryBuilder->field($column);
        } else {
            throw new \Exception("Create constraint '{$info['name']}' failed.  Missing column.");
        }
        $sql = 'ALTER TABLE '.$queryBuilder->schemaName($info['table']).' ADD CONSTRAINT '.$queryBuilder->field($constraintName)." {$info['type']} (".$column.')';
        if (array_key_exists('references', $info)) {
            if (!array_key_exists('table', $info['references']) || !array_key_exists('column', $info['references'])) {
                throw new \Exception("Create constraint '{$info['name']}' failed.  Missing references table or column.");
            }
            $sql .= ' REFERENCES '.$queryBuilder->schemaName($info['references']['table']).' ('.$queryBuilder->field($info['references']['column']).") ON UPDATE {$info['update_rule']} ON DELETE {$info['delete_rule']}";
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropConstraint(string $constraintName, string $tableName, bool $ifExists = false, bool $cascade = false): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        $sql = 'ALTER TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $queryBuilder->schemaName($tableName).' DROP CONSTRAINT ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $queryBuilder->field($constraintName).($cascade ? ' CASCADE' : '');
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }
}
