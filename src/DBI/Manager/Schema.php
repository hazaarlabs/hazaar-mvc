<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Manager\Schema\Version;

class Schema
{
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

        return new self();
    }
}
