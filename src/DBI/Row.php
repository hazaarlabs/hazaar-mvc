<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/Statement/Model.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2019 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI;

use Hazaar\Model;

if (!defined('HAZAAR_VERSION')) {
    exit('Hazaar\DBI\Row requires Hazaar to be installed!');
}

final class Row extends Model
{
    /**
     * Row constructor.
     *
     * @param array<string,\stdClass> $meta
     */
    protected function construct(
        array &$data,
        array $meta = [],
    ): void {
        foreach ($meta as $propertyName => $propertyMeta) {
            $this->defineProperty($propertyMeta->type, $propertyName);
        }
    }

    protected function constructed(): void
    {
        $this->defineEventHook('written', function ($propertyValue, $propertyName) {
            $this->changedProperties[] = $propertyName;
        });
    }
}
