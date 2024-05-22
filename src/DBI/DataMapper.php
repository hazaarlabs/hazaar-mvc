<?php

declare(strict_types=1);

namespace Hazaar\DBI;

class DataMapper
{
    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function map(array $data, mixed $map = null): array
    {
        if (!(is_iterable($map) || $map instanceof \stdClass)) {
            return $data;
        }
        foreach ($data as &$row) {
            foreach ($map as $mode => $info) {
                foreach ($info as $target => $source) {
                    if ('replace' === $mode || 'copy' === $mode) {
                        if (!array_key_exists($source, $row)) {
                            continue;
                        }
                        $row[$target] = $row[$source];
                        if ('replace' === $mode) {
                            unset($row[$source]);
                        }
                    } elseif ('set' === $mode) {
                        $row[$target] = $source;
                    }
                }
            }
        }

        return $data;
    }
}
