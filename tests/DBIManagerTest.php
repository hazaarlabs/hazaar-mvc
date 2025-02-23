<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\DBI\Manager\Migration\Action;
use Hazaar\DBI\Manager\Migration\Action\Table;
use Hazaar\DBI\Manager\Migration\Enum\ActionName;
use Hazaar\DBI\Manager\Migration\Enum\ActionType;
use Hazaar\DBI\Manager\Migration\Event;
use Hazaar\DBI\Manager\Version;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DBIManagerTest extends TestCase
{
    public function testVersion(): void
    {
        $version = Version::create();
        $this->assertIsInt($version->number);
        $this->assertTrue(isset($version->comment));
        $this->assertTrue($version->valid);
    }

    public function testActionEvent(): void
    {
        $event = new Event();
        $data = new Table([
            'name' => 'test_table',
            'columns' => [
                ['name' => 'id', 'type' => 'INT', 'primary' => true],
                ['name' => 'name', 'type' => 'TEXT'],
                ['name' => 'stored', 'type' => 'BOOLEAN', 'default' => true],
            ],
        ]);
        $action = $event->add(ActionName::CREATE, ActionType::TABLE, $data);
        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame($data, $action->spec);
        $this->assertSame(ActionName::CREATE, $action->name);
        $this->assertSame(ActionType::TABLE, $action->type);
    }

    public function testRaiseAction(): void
    {
        $event = new Event([
            'actions' => [
                [
                    'raise' => 'ERROR',
                ],
            ],
        ]);
        $this->assertCount(1, $event->actions);
        $action = $event->actions[0];
        $this->assertInstanceOf(Action::class, $action);
        $this->assertEquals('ERROR', $action->spec->message);
        $this->assertEquals(ActionName::RAISE, $action->name);
        $this->assertEquals(ActionType::ERROR, $action->type);
        $this->assertEquals(['raise' => 'ERROR'], $action->toArray());
    }
}
