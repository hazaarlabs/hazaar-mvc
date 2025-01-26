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
        if ($table) {
            list($schema, $table) = $this->queryBuilder->parseSchemaName($table);
        } else {
            $schema = $this->queryBuilder->getSchemaName();
        }
        $constraints = [];
        $sql = 'SELECT
                tc.constraint_name as name,
                tc.table_name as '.$this->queryBuilder->field('table').',
                tc.table_schema as '.$this->queryBuilder->field('schema').',
                kcu.column_name as '.$this->queryBuilder->field('column').",
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
                ON ccu.constraint_name = tc.constraint_name
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
                if ($constraint = ake($constraints, $row['name'])) {
                    if (!is_array($constraint['column'])) {
                        $constraint['column'] = [$constraint['column']];
                    }
                    if (!in_array($row['column'], $constraint['column'])) {
                        $constraint['column'][] = $row['column'];
                    }
                } else {
                    $constraint = [
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
                    $constraint['references'] = [
                        'table' => $row['foreign_table'],
                        'column' => $row['foreign_column'],
                    ];
                }
                $constraints[$row['name']] = $constraint;
            }

            return $constraints;
        }

        return false;
    }

    public function addConstraint(string $constraintName, array $info): bool
    {
        if (!array_key_exists('table', $info)) {
            return false;
        }
        if (!array_key_exists('type', $info) || !$info['type']) {
            if (array_key_exists('references', $info)) {
                $info['type'] = 'FOREIGN KEY';
            } else {
                return false;
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
        $column = $info['column'];
        if (is_array($column)) {
            foreach ($column as &$col) {
                $col = $this->queryBuilder->field($col);
            }
            $column = implode(', ', $column);
        } else {
            $column = $this->queryBuilder->field($column);
        }
        $sql = 'ALTER TABLE '.$this->queryBuilder->schemaName($info['table']).' ADD CONSTRAINT '.$this->queryBuilder->field($constraintName)." {$info['type']} (".$column.')';
        if (array_key_exists('references', $info)) {
            if (!array_key_exists('table', $info['references']) || !array_key_exists('column', $info['references'])) {
                return false;
            }
            $sql .= ' REFERENCES '.$this->queryBuilder->schemaName($info['references']['table']).' ('.$this->queryBuilder->field($info['references']['column']).") ON UPDATE {$info['update_rule']} ON DELETE {$info['delete_rule']}";
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'ALTER TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->schemaName($tableName).' DROP CONSTRAINT ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->field($constraintName).($cascade ? ' CASCADE' : '');
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }
}
