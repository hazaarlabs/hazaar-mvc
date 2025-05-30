<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Sync;

use Hazaar\DBI\Adapter;
use Hazaar\Model;
use Hazaar\Util\Boolean;

class Macro extends Model
{
    public string $table;
    public string $field;

    /**
     * @var array<string,mixed>
     */
    public array $criteria;
    public string $indexKey;

    /**
     * @var array<string,mixed>
     */
    private static $lookups = [];

    public function __toString(): string
    {
        return '::'.$this->table.($this->field ? '('.$this->field.')' : '').':'.implode(',', array_map(function ($value, $key) {
            return $key.'='.$value;
        }, $this->criteria, array_keys($this->criteria)));
    }

    public function constructed(): void
    {
        $this->indexKey = md5($this->table.'.'.$this->field.'.'.serialize($this->criteria));
    }

    public static function match(string $field): ?self
    {
        if (!preg_match('/^\:\:(\w+)\s*(\(\s*(\w+)\s*\))?\s*\:?(.*)\s*$/', $field, $matches)) {
            return null;
        }

        return new self([
            'table' => $matches[1],
            'field' => $matches[3],
            'criteria' => self::prepareCriteria($matches[4]),
        ]);
    }

    public function run(Adapter $dbi): mixed
    {
        if (isset(self::$lookups[$this->indexKey])) {
            return self::$lookups[$this->indexKey];
        }
        $row = $dbi->table($this->table)->findOne($this->criteria, $this->field);
        if (!($row && isset($row[$this->field]))) {
            throw new \Exception('Macro lookup failed: '.$this);
        }

        return self::$lookups[$this->indexKey] = $row[$this->field];
    }

    /**
     * @return array<string,mixed>
     */
    private static function prepareCriteria(string $value): array
    {
        $criteria = [];
        // Split string at , with no whitespace
        $criteriaItems = preg_split('/\s*,\s*/', trim($value));
        foreach ($criteriaItems as $criteriaItem) {
            $criteria = array_merge($criteria, self::prepareCriteriaItem($criteriaItem));
        }

        return $criteria;
    }

    /**
     * @return array<string,mixed>
     */
    private static function prepareCriteriaItem(string $criteria): array
    {
        // Split string at = with no whitespace
        [$field, $value] = preg_split('/\s*=\s*/', $criteria);
        // Remove quotes from value
        $firstChar = substr($value, 0, 1);
        if (('"' === $firstChar || "'" === $firstChar) && substr($value, -1) === $firstChar) {
            $value = substr($value, 1, -1);
        } elseif (false !== strpos($value, '.')) {
            $value = (float) $value;
        } elseif (is_numeric($value)) {
            $value = (int) $value;
        } elseif ('null' === strtolower($value)) {
            $value = null;
        } elseif (Boolean::is($value)) {
            $value = Boolean::from($value);
        }

        return [$field => $value];
    }
}
