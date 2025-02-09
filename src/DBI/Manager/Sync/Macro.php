<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Sync;

use Hazaar\DBI\Adapter;
use Hazaar\Model;

class Macro extends Model
{
    public string $table;
    public string $field;
    public string $criteria;
    public string $indexKey;

    /**
     * @var array<string,mixed>
     */
    private static $lookups = [];

    public function constructed(): void
    {
        $this->indexKey = md5($this->table.'.'.$this->field.'.'.$this->criteria);
    }

    public static function match(string $field): ?self
    {
        if (!preg_match('/^\:\:(\w+)(\((\w+)\))?\:?(.*)$/', $field, $matches)) {
            return null;
        }

        return new self([
            'table' => $matches[1],
            'field' => $matches[3],
            'criteria' => $matches[4],
        ]);
    }

    public function run(Adapter $dbi): ?string
    {
        if (isset(self::$lookups[$this->indexKey])) {
            return self::$lookups[$this->indexKey];
        }
        $row = $dbi->table($this->table)->findOne($this->criteria, $this->field);
        if (!($row && isset($row[$this->field]))) {
            return null;
        }

        return self::$lookups[$this->indexKey] = $row[$this->field];
    }
}
