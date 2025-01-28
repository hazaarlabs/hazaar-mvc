<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Manager\Migration\Action;
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
        $schema = new self();
        $schema->extensions = $dbi->listExtensions();
        $tables = $dbi->listTables();
        foreach ($tables as $table) {
            $schema->tables[] = new Table([
                'name' => $table['name'],
                'columns' => $dbi->describeTable($table['name']),
            ]);
        }
        $constraints = $dbi->listConstraints();
        foreach ($constraints as $name => $constraint) {
            $schema->constraints[] = new Constraint($constraint);
        }
        $indexes = $dbi->listIndexes();
        foreach ($indexes as $name => $index) {
            $schema->indexes[] = new Index($index);
        }
        $functions = $dbi->listFunctions();
        foreach ($functions as $functionName) {
            $functionInstances = $dbi->describeFunction($functionName);
            foreach ($functionInstances as $function => $functionInstance) {
                $schema->functions[] = new Func($functionInstance);
            }
        }
        $triggers = $dbi->listTriggers();
        foreach ($triggers as $trigger) {
            $schema->triggers[] = new Trigger($dbi->describeTrigger($trigger['name']));
        }
        $views = $dbi->listViews();
        foreach ($views as $view) {
            $schema->views[] = new View($dbi->describeView($view['name']));
        }

        return $schema;
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
        if (!isset($this->{$elementName}[$action->spec->name])) {
            throw new \Exception(ucfirst($action->type->value).' does not exist: '.$action->spec->name);
        }
        unset($this->{$elementName}[$action->spec->name]);
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
