<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Action;
use Hazaar\DBI\Manager\Migration\Action\BaseAction;
use Hazaar\DBI\Manager\Migration\Action\Component\BaseComponent;
use Hazaar\DBI\Manager\Migration\Action\Constraint;
use Hazaar\DBI\Manager\Migration\Action\Func;
use Hazaar\DBI\Manager\Migration\Action\Index;
use Hazaar\DBI\Manager\Migration\Action\Table;
use Hazaar\DBI\Manager\Migration\Action\Trigger;
use Hazaar\DBI\Manager\Migration\Action\View;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
use Hazaar\Model;

class Schema extends Model
{
    /**
     * @var array<string>
     */
    public array $extensions = [];

    /**
     * @var array<Table>
     */
    public array $tables = [];

    /**
     * @var array<View>
     */
    public array $views = [];

    /**
     * @var array<Constraint>
     */
    public array $constraints = [];

    /**
     * @var array<Index>
     */
    public array $indexes = [];

    /**
     * @var array<Func>
     */
    public array $functions = [];

    /**
     * @var array<Trigger>
     */
    public array $triggers = [];

    /**
     * @var array<string>
     */
    private static array $ignoreTables = [
        'schema_version',
        '__hz_file',
        '__hz_file_chunk',
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
            $schema->applyVersion($version);
        }

        return $schema;
    }

    public static function import(Adapter $dbi): self
    {
        $schema = [
            'extensions' => $dbi->listExtensions(),
            'tables' => [],
            'constraints' => [],
            'indexes' => [],
            'functions' => [],
            'triggers' => [],
            'views' => [],
        ];
        $tables = $dbi->listTables();
        foreach ($tables as $table) {
            if (in_array($table['name'], self::$ignoreTables)) {
                continue;
            }
            $schema['tables'][] = [
                'name' => $table['name'],
                'columns' => $dbi->describeTable($table['name']),
            ];
        }
        $constraints = $dbi->listConstraints();
        foreach ($constraints as $name => $constraint) {
            if (in_array($constraint['table'], self::$ignoreTables)) {
                continue;
            }
            $schema['constraints'][] = $constraint;
        }
        $indexes = $dbi->listIndexes();
        foreach ($indexes as $name => $index) {
            if (in_array($index['table'], self::$ignoreTables)) {
                continue;
            }
            $schema['indexes'][] = $index;
        }
        $functions = $dbi->listFunctions();
        foreach ($functions as $functionName) {
            $functionInstances = $dbi->describeFunction($functionName);
            foreach ($functionInstances as $function => $functionInstance) {
                $schema['functions'][] = $functionInstance;
            }
        }
        $triggers = $dbi->listTriggers();
        foreach ($triggers as $trigger) {
            $schema['triggers'][] = $dbi->describeTrigger($trigger['name']);
        }
        $views = $dbi->listViews();
        foreach ($views as $view) {
            $schema['views'][] = $dbi->describeView($view['name']);
        }

        return new self($schema);
    }

    public function applyVersion(Version $version): void
    {
        $migrate = $version->loadMigration();
        if (!isset($migrate->up)) {
            return;
        }
        foreach ($migrate->up->actions as $action) {
            match ($action->name) {
                ActionName::CREATE => $this->create($action),
                ActionName::ALTER => $this->alter($action),
                ActionName::DROP => $this->drop($action),
                default => null
            };
        }
    }

    public function toMigration(): Migration
    {
        $migration = new Migration();
        $migration->up = new Migration\Event();
        if (count($this->extensions) > 0) {
            $migration->up->actions[] = Action::create(ActionType::EXTENSION, $this->extensions);
        }
        foreach ($this->tables as $table) {
            $migration->up->actions[] = Action::create(ActionType::TABLE, $table);
        }
        foreach ($this->views as $view) {
            $migration->up->actions[] = Action::create(ActionType::VIEW, $view);
        }
        foreach ($this->constraints as $constraint) {
            if (isset($constraint->type) && 'PRIMARY KEY' === $constraint->type) {
                $table = self::findActionOrComponent($constraint->table, $this->tables);
                if ($table) {
                    foreach ($constraint->column as $column) {
                        $column = self::findActionOrComponent($column, $table->columns);
                        if ($column) {
                            $column->primarykey = true;
                        }
                    }

                    continue;
                }
            }
            $migration->up->actions[] = Action::create(ActionType::CONSTRAINT, $constraint);
        }
        foreach ($this->indexes as $index) {
            $migration->up->actions[] = Action::create(ActionType::INDEX, $index);
        }
        foreach ($this->functions as $function) {
            $migration->up->actions[] = Action::create(ActionType::FUNC, $function);
        }
        foreach ($this->triggers as $trigger) {
            $migration->up->actions[] = Action::create(ActionType::TRIGGER, $trigger);
        }

        return $migration;
    }

    /**
     * @param array<Action|BaseAction|BaseComponent> $haystack
     */
    public static function findActionOrComponent(string $name, array $haystack): null|Action|BaseAction|BaseComponent
    {
        foreach ($haystack as $action) {
            if ($action instanceof Action
                && isset($action->spec, $action->spec->name)
                && $action->spec->name === $name) {
                return $action;
            }
            if (($action instanceof BaseAction || $action instanceof BaseComponent)
                && $action->name === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Creates a new element in the schema.
     *
     * @param Action $action the action containing the specifications for the element to be created
     *
     * @throws \Exception if the element type is unknown or if the element already exists
     */
    private function create(Action $action): void
    {
        if (ActionType::EXTENSION === $action->type) {
            foreach ($action->spec->extensions as $extentionName) {
                if (in_array($extentionName, $this->extensions)) {
                    continue;
                }
                $this->extensions[] = $extentionName;
            }

            return;
        }
        $elementName = $this->getElementName($action->type);
        if (!isset($this->{$elementName})) {
            throw new \Exception('Unknown element type: '.$action->type->value);
        }
        if (!isset($action->spec->name)) {
            throw new \Exception('Invalid action spec for create action.  Needs a name.');
        }
        if (isset($this->{$elementName}[$action->spec->name])) {
            throw new \Exception(ucfirst($action->type->value).' already exists: '.$action->spec->name);
        }
        $this->{$elementName}[$action->spec->name] = $action->spec;
    }

    private function alter(Action $action): void
    {
        if (ActionType::EXTENSION === $action->type) {
            return;
        }
        $elementName = $this->getElementName($action->type);
        if (!isset($this->{$elementName})) {
            throw new \Exception('Unknown element type: '.$action->type->value);
        }
        if (!isset($this->{$elementName}[$action->spec->name])) {
            throw new \Exception(ucfirst($action->type->value).' does not exist: '.$action->spec->name);
        }
        $this->{$elementName}[$action->spec->name]->apply($action->spec);
    }

    /**
     * Drops an element based on the provided action.
     *
     * @param Action $action the action containing the type and specifications for the element to drop
     *
     * @throws \Exception if the element type is unknown or the specified element does not exist
     */
    private function drop(Action $action): void
    {
        if (ActionType::EXTENSION === $action->type) {
            foreach ($action->spec->extensions as $extentionName) {
                if (in_array($extentionName, $this->extensions)) {
                    continue;
                }
                $this->extensions[] = $extentionName;
            }

            return;
        }
        $elementName = $this->getElementName($action->type);
        if (!isset($this->{$elementName})) {
            throw new \Exception('Unknown element type: '.$action->type->value);
        }
        if (!isset($action->spec->drop)) {
            if (!isset($action->spec->name)) {
                throw new \Exception('Invalid action spec for drop action.  Needs array of names to drop.');
            }
            $action->spec->drop = [$action->spec->name];
        }
        foreach ($action->spec->drop as $dropItem) {
            if (!isset($this->{$elementName}[$dropItem])) {
                throw new \Exception(ucfirst($action->type->value).' does not exist: '.$dropItem);
            }

            unset($this->{$elementName}[$dropItem]);
            // If we are dropping a table, we need to drop any constraints or indexes that reference it as well
            if ('tables' === $elementName) {
                $this->dropTableReferences($this->constraints, $dropItem);
                $this->dropTableReferences($this->indexes, $dropItem);
            }
        }
    }

    /**
     * Drops all constraints or indexes that reference the specified table.
     *
     * @param array<Constraint|Index> $elements the array of constraints or indexes to search for references
     */
    private function dropTableReferences(array &$elements, string $table): void
    {
        foreach ($elements as $key => $element) {
            if ($element->table !== $table) {
                continue;
            }
            unset($elements[$key]);
        }
    }

    /**
     * Get the name of the element based on the action type.
     *
     * @param ActionType $type the type of action to get the element name for
     *
     * @return string the name of the element corresponding to the action type
     *
     * @throws \Exception if the action type is unknown
     */
    private function getElementName(ActionType $type): string
    {
        return match ($type) {
            ActionType::EXTENSION => 'extensions',
            ActionType::TABLE => 'tables',
            ActionType::VIEW => 'views',
            ActionType::CONSTRAINT => 'constraints',
            ActionType::INDEX => 'indexes',
            ActionType::FUNC => 'functions',
            ActionType::TRIGGER => 'triggers',
            default => throw new \Exception('Unknown element type: '.$type->value),
        };
    }
}
