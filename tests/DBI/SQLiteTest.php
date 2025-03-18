<?php

declare(strict_types=1);

namespace Hazaar\Tests\DBI;

use Hazaar\DBI\Adapter;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SQLiteTest extends TestCase
{
    private Adapter $db;

    // setup
    public function setUp(): void
    {
        $this->db = new Adapter(['type' => 'sqlite', 'database' => ':memory:']);
        $this->db->exec('CREATE TABLE IF NOT EXISTS test_table (id INTEGER NOT NULL PRIMARY KEY, name TEXT DEFAULT \'none\', stored BOOLEAN DEFAULT TRUE)');
    }

    // teardown
    public function tearDown(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS test_table');
        unset($this->db);
    }

    public function testTables(): void
    {
        $this->assertTrue($this->db->table('test_table')->exists());
        $this->assertEquals([['name' => 'test_table']], $this->db->listTables());
        $columns = [
            [
                'name' => 'id',
                'default' => null,
                'not_null' => true,
                'type' => 'integer',
                'length' => null,
                'primarykey' => true,
            ],
            [
                'name' => 'name',
                'default' => "'none'",
                'not_null' => false,
                'type' => 'text',
                'length' => null,
            ],
            [
                'name' => 'stored',
                'default' => 'true',
                'not_null' => false,
                'type' => 'boolean',
                'length' => null,
            ],
        ];
        $this->assertEquals($columns, $this->db->describeTable('test_table'));
    }

    public function testRawSQL(): void
    {
        $inserted = $this->db->exec('INSERT INTO test_table (name) VALUES ("test")');
        $this->assertEquals(1, $inserted);
        $result = $this->db->query('SELECT * FROM test_table');
        $this->assertIsObject($result);
        // $this->assertEquals(1, $result->count());
        $row = $result->fetch();
        $this->assertIsArray($row);
        $this->assertEquals('test', $row['name']);
        unset($row, $result); // Unlock the result set so we can drop the table
    }

    public function testTableInsert(): void
    {
        // Setup
        $table = $this->db->table('test_table');
        $this->assertInstanceOf('Hazaar\DBI\Table', $table);
        $this->assertEquals('test_table', $table->getName());
        // Insert
        $rowId = rand(1, 1000);
        $newRowId = $table->insert(['name' => 'test', 'id' => $rowId], 'id');
        $this->assertIsInt($newRowId);
        $this->assertGreaterThan(0, $rowId);
        $this->assertEquals($rowId, $newRowId);
    }

    public function testTableFindOne(): void
    {
        // Setup
        $table = $this->db->table('test_table');
        $this->assertInstanceOf('Hazaar\DBI\Table', $table);
        $this->assertEquals('test_table', $table->getName());
        $rowId = rand(1, 1000);
        $this->assertEquals($rowId, $table->insert(['name' => 'test', 'id' => $rowId], 'id'));
        // Select
        $result = $table->findOne(['id' => $rowId, ['name' => 'test']]);
        $this->assertIsArray($result);
        $this->assertEquals('test', $result['name']);
    }

    public function testTableUpdate(): void
    {
        // Setup
        $table = $this->db->table('test_table');
        $this->assertInstanceOf('Hazaar\DBI\Table', $table);
        $this->assertEquals('test_table', $table->getName());
        $rowId = rand(1, 1000);
        // Insert
        $this->assertEquals($rowId, $table->insert(['name' => 'test', 'id' => $rowId], 'id'));
        // Update
        $updated = $table->update(['name' => 'test2'], ['id' => $rowId]);
        $this->assertEquals(1, $updated);
        $result = $table->findOne(['id' => $rowId]);
        $this->assertIsArray($result);
        $this->assertEquals('test2', $result['name']);
    }

    public function testTableDelete(): void
    {
        // Setup
        $table = $this->db->table('test_table');
        $this->assertInstanceOf('Hazaar\DBI\Table', $table);
        $this->assertEquals('test_table', $table->getName());
        $rowId = rand(1, 1000);
        // Insert
        $this->assertEquals($rowId, $table->insert(['name' => 'test', 'id' => $rowId], 'id'));
        // Delete
        $deleted = $table->delete(['id' => $rowId]);
        $this->assertEquals(1, $deleted);
        $result = $table->findOne(['id' => $rowId]);
        $this->assertFalse($result);
    }

    public function testInsertSelect(): void
    {
        $rowId = rand(1, 1000);
        $sql = 'SELECT * FROM "test_table" WHERE id = :id0';
        $data = [
            'id' => $rowId,
            'name' => 'Test Name',
            'stored' => true,
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $statement = $this->db->table('test_table')->find(['id' => $rowId]);
        $this->assertEquals($sql, (string) $statement);
        $this->assertEquals($data, $statement->fetch());
    }

    public function testTransaction(): void
    {
        $this->db->begin();
        $this->db->exec('INSERT INTO test_table (name) VALUES ("test")');
        $this->db->cancel();
        $result = $this->db->query('SELECT * FROM test_table');
        $this->assertFalse($result->fetch());
        $this->db->begin();
        $this->db->exec('INSERT INTO test_table (name) VALUES ("test")');
        $this->db->commit();
        $result = $this->db->query('SELECT * FROM test_table');
        $this->assertIsArray($result->fetch());
    }

    public function testTriggers(): void
    {
        $spec = [
            'timing' => 'BEFORE',
            'events' => 'INSERT',
            'content' => 'BEGIN INSERT INTO test_table (name) VALUES ("triggered"); END;',
        ];
        $result = $this->db->createTrigger('test_trigger', 'test_table', $spec);
        $this->assertTrue($result);
        $table = $this->db->table('test_table');
        $rowId = $table->insert(['name' => 'test'], 'id');
        $result = $table->findOne(['id' => $rowId]);
        $this->assertIsArray($result);
        $triggered = $table->findOne(['name' => 'triggered']);
        $this->assertEquals('triggered', $triggered['name']);
        $result = $this->db->dropTrigger('test_trigger', 'test_table', true);
        $this->assertTrue($result);
    }

    public function testPreparedStatements(): void
    {
        $statement = $this->db->prepare('INSERT INTO test_table (name) VALUES (:name)');
        $this->assertInstanceOf('Hazaar\DBI\Statement', $statement);
        $this->assertEquals(1, $statement->execute(['name' => 'test']));
        $result = $this->db->query('SELECT * FROM test_table');
        $this->assertIsArray($result->fetch());
    }
}
