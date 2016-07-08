<?php

namespace Hazaar\DBI\DBD;

class Pgsql extends BaseDriver {

    public function __construct($config){

        parent::__construct($config);

        $this->schema = 'public';

    }

    public function connect($dsn, $username = null, $password = null, $driver_options = null) {

        $d_pos = strpos($dsn, ':');

        $driver = strtolower(substr($dsn, 0, $d_pos));

        if (!$driver == 'pgsql')
            return false;

        $dsn_parts = array_unflatten(substr($dsn, $d_pos + 1));

        if (!array_key_exists('user', $dsn_parts))
            $dsn_parts['user'] = $username;

        if (!array_key_exists('password', $dsn_parts))
            $dsn_parts['password'] = $password;

        $dsn = $driver . ':' . array_flatten($dsn_parts);

        return parent::connect($dsn, null, null, $driver_options);

    }

    public function field($string) {

        if (strpos($string, '.') !== false || strpos($string, '(') !== false)
            return $string;

        return '"' . $string . '"';

    }

    public function listIndexes($table) {

        $indexes = array();

        $sql = "
            SELECT
                c.relname as name,
                idx.indrelid::regclass as table,
                am.amname as using,
                ARRAY(SELECT pg_get_indexdef(idx.indexrelid, k + 1, true) FROM generate_subscripts(idx.indkey, 1) as k ORDER BY k) as columns,
                idx.indexprs IS NOT NULL as indexprs,
                idx.indpred IS NOT NULL as indpred,
                idx.indisunique as unique
            FROM pg_index idx
            INNER JOIN pg_class c ON c.oid = idx.indexrelid
            INNER JOIN pg_am am ON c.relam = am.oid
            WHERE idx.indisprimary = FALSE
                AND (idx.indrelid::regclass)::text = '$table';";

        $result = $this->query($sql);

        while($row = $result->fetch(\PDO::FETCH_ASSOC)) {

            $row['columns'] = (explode(',', substr($row['columns'], 1, strlen($row['columns']) - 2)));

            $indexes[] = $row;

        }

        return $indexes;

    }

    public function createIndex($idx_info, $table, $schema = null) {

        if (!array_key_exists('name', $idx_info))
            return false;

        if (!array_key_exists('table', $idx_info))
            return false;

        if (!array_key_exists('columns', $idx_info))
            return false;

        $sql = 'CREATE';

        if (array_key_exists('unique', $idx_info) && $idx_info['unique'])
            $sql .= ' UNIQUE';

        $sql .= ' INDEX ' . $idx_info['name'] . ' ON ' . $idx_info['table'];

        if (array_key_exists('using', $idx_info) && $idx_info['using'])
            $sql .= ' USING ' . $idx_info['using'];

        $sql .= ' (' . implode(',', $idx_info['columns']) . ');';

        $affected = $this->exec($sql);

        if ($affected === false)
            return false;

        return true;

    }

}


