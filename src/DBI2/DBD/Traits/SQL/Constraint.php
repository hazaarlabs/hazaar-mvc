<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Constraint
{
    use Schema;

    /**
     * @return array<string>|false
     */
    public function listConstraints(
        ?string $tableName = null,
        ?string $type = null,
        bool $invertType = false
    ): array|false {
        $criteria = ['constraint_schema' => $this->queryBuilder->getSchemaName()];
        if ($tableName) {
            $criteria['table_name'] = $tableName;
        }
        if ($type) {
            $criteria['constraint_type'] = $type;
        }
        $constraints = $this->listInformationSchema('table_constraints', ['constraint_name'], $criteria);
        if ($invertType) {
            $constraints = array_diff($this->listInformationSchema('table_constraints', ['constraint_name'], $criteria), $constraints);
        }

        return $constraints;
    }

    public function addConstraint(string $constraintName, mixed $info): bool
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
        if ('FOREIGN KEY' == $info['type']) {
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
