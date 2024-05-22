<?php

declare(strict_types=1);

namespace Hazaar\DBI\Table;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Table;

class SQL extends Table
{
    /**
     * @var array<string>
     */
    private static array $keywords = [
        'SELECT',
        'FROM',
        'WHERE',
        'GROUP BY',
        'HAVING',
        'WINDOW',
        'UNION',
        'INTERSECT',
        'EXCEPT',
        'ORDER BY',
        'LIMIT',
        'OFFSET',
        'FETCH',
    ];
    private string $sql;

    public function __construct(Adapter $dbi, string $sql)
    {
        parent::__construct($dbi);
        $this->parse($sql);
    }

    public function parse(string $sql): bool
    {
        if ('SELECT' !== strtoupper(substr(trim($sql), 0, 6))) {
            return false;
        }
        $this->sql = rtrim(trim($sql), ';');
        $chunks = $this->splitWordBoundaries($this->sql, self::$keywords);
        foreach ($chunks as $keyword => $values) {
            foreach ($values as $chunk) {
                $method = 'process'.str_replace(' ', '_', $keyword);
                if (method_exists($this, $method)) {
                    call_user_func([$this, $method], $chunk);
                }
            }
        }

        return true;
    }

    public function processSELECT(string $line): void
    {
        $this->fields = [];
        $parts = preg_split('/\s*,\s*/', $line);
        foreach ($parts as $part) {
            if ($chunks = $this->splitWordBoundaries($part, ['AS'], $pos)) {
                $this->fields[ake($chunks['AS'], 0)] = trim(substr($part, 0, $pos));
            } else {
                $this->fields[] = $part;
            }
        }
    }

    public function processFROM(string $line): void
    {
        $keywords = [
            'CROSS',
            'INNER',
            'LEFT',
            'RIGHT',
            'FULL',
            'OUTER',
            'NATURAL',
            'JOIN',
        ];
        if (!($chunks = $this->splitWordBoundaries($line, $keywords, $pos))) {
            $pos = strlen($line);
        } elseif (!$pos > 0) {
            throw new \Exception('Parse error.  Got JOIN on missing table.');
        }
        $parts = preg_split('/\s+/', trim(substr($line, 0, $pos)), 2);
        if (array_key_exists(0, $parts)) {
            $this->tableName = ake($parts, 0);
        }
        if (array_key_exists(1, $parts)) {
            $this->alias = ake($parts, 1);
        }
        if (0 == count($chunks)) {
            return;
        }
        reset($chunks);
        while (null !== key($chunks)) {
            $pos = null;
            $references = null;
            $alias = null;
            $type = key($chunks);
            if ('JOIN' !== $type) {
                next($chunks);
                if ('JOIN' !== key($chunks)) {
                    throw new \Exception('Parse error.  Expecting JOIN');
                }
            }

            foreach (current($chunks) as $id => $join) {
                $parts = $this->splitWordBoundaries($join, ['ON'], $pos);
                if (!$pos > 0) {
                    throw new \Exception('Parse error.  Expecting join table name!');
                }
                $join_parts = preg_split('/\s+/', trim(substr($join, 0, $pos)), 2);
                $references = ake($join_parts, 0);
                $alias = ake($join_parts, 1, $references);
                $this->joins[$alias] = [
                    'type' => $type,
                    'ref' => $references,
                    'on' => $this->parseCondition($parts['ON'][0], true),
                    'alias' => $alias,
                ];
            }

            next($chunks);
        }
    }

    public function processWHERE(string $line): void
    {
        $line = trim($line);
        if (!('(' === substr($line, 0, 1) && ')' === substr($line, -1, 1))) {
            $line = '('.$line.')';
        }
        $this->criteria = $this->parseCondition($line);
    }

    public function processGROUP_BY(string $line): void
    {
        $this->group = preg_split('/\s*,\s*/', $line);
    }

    public function processHAVING(string $line): void
    {
        $this->having = $this->parseCondition($line);
    }

    public function parseUNION(string $line): void
    {
        if (!array_key_exists('union', $this->combine)) {
            $this->combine['union'] = [];
        }
        $this->combine['union'] = new SQL($this->adapter, $line);
    }

    public function processINTERSECT(string $line): void
    {
        if (!array_key_exists('intersect', $this->combine)) {
            $this->combine['intersect'] = [];
        }
        $this->combine['intersect'] = new SQL($this->adapter, $line);
    }

    public function processEXCEPT(string $line): void
    {
        if (!array_key_exists('except', $this->combine)) {
            $this->combine['except'] = [];
        }
        $this->combine['except'] = new SQL($this->adapter, $line);
    }

    public function processORDER_BY(string $line): void
    {
        $parts = preg_split('/\s+/', $line, 2);
        if (array_key_exists(0, $parts)) {
            $this->order = [
                $parts[0] => (array_key_exists(1, $parts) && 'DESC' === strtoupper(trim($parts[1])) ? -1 : 1),
            ];
        }
    }

    public function processLIMIT(string $line): void
    {
        $this->limit = 'ALL' === strtoupper($line) ? null : (int)$line;
    }

    public function processOFFSET(string $line): void
    {
        $parts = preg_split('/\s+/', $line, 2);
        if (array_key_exists(0, $parts)) {
            $this->offset = (int)$parts[0];
        }
    }

    public function processFETCH(string $line): void
    {
        $fetch_def = [];
        $parts = preg_split('/\s+/', $line);
        if (array_key_exists(0, $parts)) {
            $fetch_def['which'] = $parts[0];
        }
        if (array_key_exists(1, $parts) && is_numeric($parts[1])) {
            $fetch_def['count'] = (int)$parts[1];
        }
        if (count($fetch_def) > 0) {
            $this->fetch = $fetch_def;
        }
    }

    /**
     * @param array<string> $keywords
     *
     * @return array<string, array<int, string>>
     */
    private function splitWordBoundaries(string $string, array $keywords, ?int &$start_pos = null): array
    {
        $chunks = [];
        $start_pos = null;
        foreach ($keywords as $keyword) {
            if (0 === preg_match_all("/\\b{$keyword}\\b/i", $string, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as $match) {
                $pos = $match[1] + ((' ' === substr($match[0], 0, 1)) ? 1 : 0);
                if (null === $start_pos) {
                    $start_pos = $pos;
                }
                if (!array_key_exists($keyword, $chunks)) {
                    $chunks[$keyword] = [];
                }
                $chunks[$keyword][] = $pos;
            }
        }
        $result = [];
        foreach ($chunks as $keyword => &$positions) {
            foreach ($positions as &$pos) {
                $next = strlen($string);
                // Find the next position.  We intentionally do this instead of a sort so that SQL is processed in a known order.
                foreach (array_values($chunks) as $values) {
                    foreach ($values as $value) {
                        if (is_int($value) && ($value <= $pos || $value >= $next)) {
                            continue;
                        }
                        $next = $value;
                    }
                }
                if (!array_key_exists($keyword, $result)) {
                    $result[$keyword] = [];
                }
                $result[$keyword][] = trim(substr($string, $pos + strlen($keyword), $next - ($pos + strlen($keyword))));
            }
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function parseCondition(string $line, bool $use_refs = false): array
    {
        $symbols = [];
        while (preg_match('/(\((?:\(??[^\(]*?\)))+/', $line, $chunks)) {
            $id = uniqid();
            $symbols[$id] = $this->parseCondition(trim($chunks[0], '()'));
            $line = str_replace($chunks[0], $id, $line);
        }
        $delimeters = ['and', 'or'];
        $parts = preg_split('/\b('.implode('|', $delimeters).')\b/i', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        $conditions = array_combine($delimeters, array_fill(0, count($delimeters), []));
        if (count($parts) > 1) {
            for ($i = 0; $i < count($parts); ++$i) {
                if (!($glu = strtolower(ake($parts, $i + 1)))) {
                    $glu = strtolower(ake($parts, $i - 1));
                }
                $conditions['$'.$glu][] = array_unflatten($parts[$i]);
                ++$i;
            }
        } else {
            $conditions['$and'][] = array_unflatten($parts[0]);
        }
        array_remove_empty($conditions);
        $root = uniqid();
        $symbols[$root] = $conditions;
        foreach ($symbols as $id => &$symbol) {
            foreach ($symbol as $glu => &$chunk) {
                if (!is_array($chunk)) {
                    $glu = '$and';
                    $chunk = [$chunk];
                }
                foreach ($chunk as &$condition) {
                    foreach ($condition as $key => &$value) {
                        if ("'" === substr($value, 0, 1) && "'" === substr($value, -1, 1)) {
                            $value = substr($value, 1, -1);
                        } elseif (is_numeric($value)) {
                            if (false === strpos($value, '.')) {
                                $value = (int)$value;
                            } else {
                                $value = floatval($value);
                            }
                        } elseif (is_boolean($value)) {
                            $value = boolify($value);
                        } elseif (array_key_exists($value, $symbols)) {
                            $condition[$key] = &$symbols[$value];
                        } elseif (true === $use_refs) {
                            $condition[$key] = ['$ref' => $value];
                        }
                    }
                }
            }
        }

        return $symbols[$root];
    }
}
