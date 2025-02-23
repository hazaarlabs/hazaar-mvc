<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait Trigger
{
    /**
     * List defined triggers.
     *
     * @return array<int,array{schema:string,name:string}>
     */
    public function listTriggers(?string $tableName = null): array
    {
        $sql = 'SELECT DISTINCT trigger_schema AS schema, trigger_name AS name
                    FROM INFORMATION_SCHEMA.triggers
                    WHERE event_object_schema='.$this->queryBuilder->prepareValue($this->queryBuilder->getSchemaName());
        if (null !== $tableName) {
            $sql .= ' AND event_object_table='.$this->queryBuilder->prepareValue($tableName);
        }
        if ($result = $this->query($sql)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    public function triggerExists(string $triggerName, string $tableName): bool
    {
        return in_array($triggerName, $this->listTriggers($tableName));
    }

    /**
     * Describe a database trigger.
     *
     * This will return an array as there can be multiple triggers with the same name but with different attributes
     *
     * @param string $schemaName Optional: schemaName name.  If not supplied the current schemaName is used.
     *
     * @return array{
     *  name:string,
     *  events:array<string>,
     *  table:string,
     *  content:string,
     *  orientation:string,
     *  timing:string
     * }|false
     */
    public function describeTrigger(string $triggerName, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->queryBuilder->getSchemaName();
        }
        $sql = 'SELECT trigger_name AS name,
                        event_manipulation AS events,
                        event_object_table AS table,
                        action_statement AS content,
                        action_orientation AS orientation,
                        action_timing AS timing
                    FROM INFORMATION_SCHEMA.triggers
                    WHERE trigger_schema='.$this->queryBuilder->prepareValue($schemaName)
                    .' AND trigger_name='.$this->queryBuilder->prepareValue($triggerName);
        if (!($result = $this->query($sql))) {
            return false;
        }
        $info = $result->fetch(\PDO::FETCH_ASSOC);
        $info['events'] = [$info['events']];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $info['events'][] = $row['events'];
        }

        return $info;
    }

    /**
     * Summary of createTrigger.
     *
     * @param string $tableName The table on which the trigger is being created
     * @param mixed  $spec      The spec of the trigger.  Basically this is the array returned from describeTriggers()
     */
    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool
    {
        $sql = 'CREATE TRIGGER '.$this->queryBuilder->field($triggerName)
            .' '.ake($spec, 'timing', 'BEFORE')
            .' '.implode(' OR ', ake($spec, 'events', ['INSERT']))
            .' ON '.$this->queryBuilder->schemaName($tableName)
            .' FOR EACH '.ake($spec, 'orientation', 'ROW');
        $execute = preg_replace_callback('/FUNCTION\s+([^\s\(]+)/i', function ($match) {
            return 'FUNCTION ' . $this->queryBuilder->schemaName($match[1]);
        }, ake($spec, 'content', 'EXECUTE'));
        $sql .= ' '.$execute;

        return false !== $this->exec($sql);
    }

    /**
     * Drop a trigger from a table.
     *
     * @param string $tableName The name of the table to remove the trigger from
     * @param bool   $cascade   Whether to drop CASCADE
     */
    public function dropTrigger(string $triggerName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'DROP TRIGGER ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->field($triggerName).' ON '.$this->queryBuilder->schemaName($tableName);
        $sql .= ' '.((true === $cascade) ? ' CASCADE' : ' RESTRICT');

        return false !== $this->exec($sql);
    }
}
