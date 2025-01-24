<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

class Schema
{
    /**
     * @var array<mixed>
     */
    public array $extensions = [];

    /**
     * @var array<mixed>
     */
    public array $sequences = [];

    /**
     * @var array<mixed>
     */
    public array $tables = [];

    /**
     * @var array<mixed>
     */
    public array $views = [];

    /**
     * @var array<mixed>
     */
    public array $constraints = [];

    /**
     * @var array<mixed>
     */
    public array $indexes = [];

    /**
     * @var array<mixed>
     */
    public array $functions = [];

    /**
     * @var array<mixed>
     */
    public array $triggers = [];

    private static array $tableMap = [
        'extension' => ['extensions', false, null],
        'sequence' => ['sequences', false, null],
        'table' => ['tables', 'cols', null],
        'view' => ['views', true, 'views'],
        'constraint' => ['constraints', true, null],
        'index' => ['indexes', true, null],
        'function' => ['functions', false, 'functions'],
        'trigger' => ['triggers', true, 'functions'],
    ];

    /**
     * Load a schema from an array of versions.
     *
     * @param array<Version> $versions
     */
    public static function load(array $versions): self
    {
        if (!count($versions)) {
            return new self();
        }
        $schema = new self();
        foreach ($versions as $version) {
            $schema->addVersion($version);
        }

        return $schema;
    }

    public function addVersion(Version $version): void
    {
        $migrate = $version->getMigrationScript();
        dump($migrate);
        if (!isset($migrate->up)) {
            return;
        }
        dump($migrate);
    }

    /**
     * @return array<mixed>|false
     * */
    public function getSchema(?int $maxVersion = null): array|false
    {
        $schema = ['version' => 0];
        foreach (self::$tableMap as $i) {
            $schema[$i[0]] = [];
        }

        /**
         * Get a list of all the available versions.
         */
        $versions = $this->getVersions(true);
        foreach ($versions as $version => $file) {
            if (null !== $maxVersion && $version > $maxVersion) {
                break;
            }
            if (!($fileContent = @file_get_contents($file))) {
                throw new \Exception('Error reading schema migration file: '.$file);
            }
            if (!($migrate = json_decode($fileContent, true))) {
                throw new \Exception('Error decoding schema migration file: '.$file);
            }
            if (!array_key_exists('up', $migrate)) {
                continue;
            }
            foreach ($migrate['up'] as $type => $actions) {
                foreach ($actions as $action => $items) {
                    if (!($map = ake(self::$tableMap, $type))) {
                        continue 2;
                    }
                    list($elem, $source, $contentType) = $map;
                    if (!array_key_exists($elem, $schema)) {
                        $schema[$elem] = [];
                    }
                    if (false !== $source) {
                        if ('alter' === $action) {
                            foreach ($items as $table => $alterations) {
                                if (true === $source) {
                                    $schema[$elem][$alterations['name']] = $alterations;
                                } else {
                                    foreach ($alterations as $altAction => $altColumns) {
                                        if ('drop' === $altAction) {
                                            if (!isset($schema['tables'][$table])) {
                                                throw new \Exception("Drop action on table '{$table}' which does not exist!");
                                            }
                                            // Remove the column from the table schema
                                            $schema['tables'][$table] = array_filter($schema['tables'][$table], function ($item) use ($altColumns) {
                                                return !in_array($item['name'], $altColumns);
                                            });
                                            // Update any constraints/indexes that reference this column
                                            if (isset($schema['constraints'])) {
                                                $schema['constraints'] = array_filter($schema['constraints'], function ($item) use ($altColumns) {
                                                    return !in_array($item['column'], $altColumns);
                                                });
                                            }
                                            if (isset($schema['indexes'])) {
                                                $schema['indexes'] = array_filter($schema['indexes'], function ($item) use ($table, $altColumns) {
                                                    return $item['table'] !== $table || 0 === count(array_intersect($item['columns'], $altColumns));
                                                });
                                            }
                                        } else {
                                            foreach ($altColumns as $colName => $colData) {
                                                if ('add' === $altAction) {
                                                    $schema['tables'][$table][] = $colData;
                                                } elseif ('alter' === $altAction && array_key_exists($table, $schema['tables'])) {
                                                    foreach ($schema['tables'][$table] as &$col) {
                                                        if ($col['name'] !== $colName) {
                                                            continue;
                                                        }
                                                        // If we are renaming the column, we need to update index and constraints
                                                        if (array_key_exists('name', $colData) && $col['name'] !== $colData['name']) {
                                                            if (isset($schema['constraints'])) {
                                                                array_walk($schema['constraints'], function (&$item) use ($colName, $colData) {
                                                                    if ($item['column'] === $colName) {
                                                                        $item['column'] = $colData['name'];
                                                                    }
                                                                });
                                                            }
                                                            if (isset($schema['indexes'])) {
                                                                array_walk($schema['indexes'], function (&$item) use ($colName, $colData) {
                                                                    if (in_array($colName, $item['columns'])) {
                                                                        $item['columns'][array_search($colName, $item['columns'])] = $colData['name'];
                                                                    }
                                                                });
                                                            }
                                                        }
                                                        // If the column data type is changing and there is no 'length' property, set the length to null.
                                                        if (array_key_exists('data_type', $colData)
                                                            && !array_key_exists('length', $colData)
                                                            && $col['data_type'] !== $colData['data_type']) {
                                                            $colData['length'] = null;
                                                        }
                                                        $col = array_merge($col, $colData);

                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            foreach ($items as $item) {
                                if ('create' === $action) {
                                    $schema[$elem][$item['name']] = (true === $source) ? $item : $item[$source];
                                } elseif ('remove' === $action) {
                                    if (is_array($item)) {
                                        if (!array_key_exists('name', $item)) {
                                            throw new \Exception('Unable to remove elements with no name');
                                        }
                                        foreach ($schema[$elem] as $index => $childItem) {
                                            if (ake($childItem, 'name') === $item['name']
                                                && ake($childItem, 'table') === ake($item, 'table')) {
                                                unset($schema[$elem][$index]);
                                            }
                                        }
                                    } else {
                                        unset($schema[$elem][$item]);
                                        if ('table' === $type) {
                                            if (isset($schema['constraints'])) {
                                                $schema['constraints'] = array_filter($schema['constraints'], function ($c) use ($item) {
                                                    return $c['table'] !== $item;
                                                });
                                            }
                                            if (isset($schema['indexes'])) {
                                                $schema['indexes'] = array_filter($schema['indexes'], function ($i) use ($item) {
                                                    return $i['table'] !== $item;
                                                });
                                            }
                                        }
                                    }
                                } else {
                                    throw new Schema("I don't know how to handle: {$action}");
                                }
                            }
                        }
                    } else {
                        foreach ($items as $itemName => $item) {
                            if (is_string($item)) {
                                if ('create' === $action) {
                                    $schema[$elem][] = $item;
                                } elseif ('remove' === $action) {
                                    foreach ($schema[$elem] as $schemaItemName => &$schemaItem) {
                                        if (is_array($schemaItem)) {
                                            if (!array_key_exists($item, $schemaItem)) {
                                                continue;
                                            }
                                            unset($schemaItem[$item]);
                                        } elseif ($schemaItem !== $item) {
                                            continue;
                                        } else {
                                            unset($schema[$elem][$schemaItemName]);
                                        }

                                        break;
                                    }
                                }
                            // Functions removed are a bit different as we have to look at parameters.
                            } elseif ('function' === $type && 'remove' === $action) {
                                if (array_key_exists($itemName, $schema[$elem])) {
                                    foreach ($item as $params) {
                                        // Find the existing function and remove it
                                        foreach ($schema[$elem][$itemName] as $index => $func) {
                                            $cParams = array_map(function ($item) {
                                                return ake($item, 'type');
                                            }, ake($func, 'parameters'));
                                            // We do an array_diff_assoc so that parameter position is taken into account
                                            if (0 === count(array_diff_assoc($params, $cParams)) && 0 === count(array_diff_assoc($cParams, $params))) {
                                                unset($schema[$elem][$itemName][$index]);
                                            }
                                        }
                                    }
                                }
                            } elseif (array_key_exists('table', $item)) {
                                if ('create' === $action || 'alter' === $action) {
                                    $schema[$elem][$item['table']][$item['name']] = $item;
                                } elseif ('remove' === $action) {
                                    unset($schema[$elem][$item['table']][$item['name']]);
                                } else {
                                    throw new Schema("I don't know how to handle: {$action}");
                                }
                            } else {
                                if ('create' === $action || 'alter' === $action) {
                                    $name = $item['name'] ?? $itemName;
                                    $schema[$elem][$name][] = $item;
                                } else {
                                    throw new Schema("I don't know how to handle: {$action}");
                                }
                            }
                        }
                        $schema[$elem] = array_filter($schema[$elem], function ($item) {
                            return is_array($item) ? count($item) > 0 : true;
                        });
                    }
                    /*
                     * For types that have content, we need to add the version to the content if
                     * it is stored in an external file.
                     */
                    if ($contentType) {
                        foreach ($schema[$elem] as &$contentItem) {
                            if (true === $source) {
                                $this->processContent($version, $contentType, $contentItem);
                            } else {
                                foreach ($contentItem as &$contentGroup) {
                                    $this->processContent($version, $contentType, $contentGroup);
                                }
                            }
                        }
                    }
                }
            }
            $schema['version'] = $version;
        }
        if (0 === $schema['version']) {
            return false;
        }
        // Remove any empty stuff
        $schema = array_filter($schema, function ($item) {
            return !is_array($item) || count($item) > 0;
        });

        return $schema;
    }
}
