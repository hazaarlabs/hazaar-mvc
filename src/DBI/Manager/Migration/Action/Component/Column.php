<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action\Component;

class Column extends BaseComponent
{
    public string $name;
    public mixed $default;
    public bool $not_null;
    public string $type;
    public bool $primarykey;

    public function changed(BaseComponent $column): ?BaseComponent
    {
        $keys = $this->keys();
        foreach ($keys as $key) {
            // If the key is not set in either column, or the values are different, return the column
            if ((isset($this->{$key}) && !isset($column->{$key}))
            || (!isset($this->{$key}) && isset($column->{$key}))
            || (isset($this->{$key}, $column->{$key}) && $this->{$key} !== $column->{$key})) {
                return $this;
            }
        }

        return null;
    }
}
