<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
use Hazaar\DBI\Manager\Migration\Interface\Spec;
use Hazaar\Model;

abstract class BaseAction extends Model implements Spec
{
    /**
     * @var array<string>
     */
    public array $drop;

    public function construct(mixed &$data): void
    {
        if (!isset($data['name']) && !isset($data['drop']) && !is_assoc($data)) {
            $data['drop'] = $data;
        }
    }

    public function run(Adapter $dbi, ActionType $type, ActionName $name): bool
    {
        return match ($name) {
            ActionName::CREATE => $this->create($dbi),
            ActionName::ALTER => $this->alter($dbi),
            ActionName::DROP => $this->drop($dbi),
            default => false,
        };
    }

    public function create(Adapter $dbi): bool
    {
        return false;
    }

    public function alter(Adapter $dbi): bool
    {
        return false;
    }

    public function drop(Adapter $dbi): bool
    {
        return false;
    }

    /**
     * Apply an ALTER action to the BaseAction.
     */
    public function apply(self $action): bool
    {
        return false;
    }

    /**
     * Find the difference between two BaseActions.
     */
    public function diff(self $action): ?self
    {
        return null;
    }

    /**
     * Return the BaseAction as an array.
     */
    public function serializeDrop(): mixed
    {
        return $this->name;
    }
}
