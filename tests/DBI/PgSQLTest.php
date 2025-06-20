<?php

declare(strict_types=1);

namespace Hazaar\Tests\DBI;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Row;
use Hazaar\Model;
use PHPUnit\Framework\TestCase;

class DBITestModel extends Model
{
    protected int $id;
    protected string $name;
    protected bool $stored;
}

/**
 * @internal
 */
class PgSQLTest extends TestCase
{
    private Adapter $db;

    public function setUp(): void
    {
        $this->db = new Adapter();
        $this->db->exec('DROP TABLE IF EXISTS test_table');
        $this->db->exec('CREATE TABLE test_table (id INT PRIMARY KEY, name TEXT, stored BOOLEAN DEFAULT TRUE, parent INT)');
    }

    public function testDatabaseConfig(): void
    {
        $this->assertEquals('pgsql', $this->db->config['type']);
        $tables = $this->db->listTables();
        $this->assertNotEmpty($tables);
        $this->assertContains(['name' => 'test_table', 'schema' => 'public'], $tables);
    }

    public function testTables(): void
    {
        $this->assertTrue($this->db->table('test_table')->exists());
        $this->assertContains(['name' => 'test_table', 'schema' => 'public'], $this->db->listTables());
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
                'default' => null,
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
            [
                'name' => 'parent',
                'default' => null,
                'not_null' => false,
                'type' => 'integer',
                'length' => null,
            ],
        ];
        $this->assertEquals($columns, $this->db->describeTable('test_table'));
    }

    public function testSelectSomeQueries(): void
    {
        $rowId = rand(1, 10000);
        $data = [
            'id' => $rowId,
            'name' => 'Test Name',
            'parent' => null,
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $result = $this->db->table('test_table')->find(['id' => $rowId]);
        $this->assertEquals($data['name'], $result->fetch()['name']);
        $result = $this->db->table('test_table')->find(['parent' => null]);
        $this->assertEquals(1, $result->count());
        $this->assertEquals($data['name'], $result->fetch()['name']);
    }

    public function testModelInsert(): void
    {
        $rowId = rand(1, 10000);
        $sql = 'INSERT INTO "public"."test_table" (id, name) VALUES (:id0, :name0) RETURNING stored';
        $data = [
            'id' => $rowId,
            'name' => 'Test Name',
            'extra' => 'extra data that will not be inserted',
        ];
        $model = new DBITestModel($data);
        $this->assertInstanceOf($model::class, $this->db->table('test_table')->insertModel($model));
        $this->assertTrue($model->stored);
        $this->assertEquals($sql, $this->db->lastQueryString());
    }

    public function testModelSelect(): void
    {
        $rowId = rand(1, 10000);
        $data = [
            'id' => $rowId,
            'name' => 'Test Name',
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $result = $this->db->table('test_table')->find(['id' => $rowId]);
        $model = $result->fetchModel(DBITestModel::class);
        $this->assertInstanceOf(DBITestModel::class, $model);
        $this->assertEquals($data['name'], $model->name);
        $this->assertEquals($data['id'], $model->id);
        $this->assertTrue($model->stored); // default value
    }

    public function testInsertSelect(): void
    {
        $rowId = rand(1, 10000);
        $sql = 'SELECT * FROM "public"."test_table" WHERE id = :id0';
        $data = [
            'id' => $rowId,
            'name' => 'Test Name',
            'stored' => true,
            'parent' => null,
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $result = $this->db->table('test_table')->find(['id' => $rowId]);
        $this->assertEquals($sql, (string) $result);
        $this->assertEquals($data, $result->fetch());
    }

    public function testSelectRow(): void
    {
        $rowId = rand(1, 10000);
        $sql = 'SELECT * FROM "public"."test_table" WHERE id = :id0';
        $data = [
            'id' => $rowId,
            'name' => 'Test Name',
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $statement = $this->db->table('test_table')->find(['id' => $rowId]);
        $this->assertEquals($sql, (string) $statement);
        $row = $statement->row();
        $this->assertInstanceOf(Row::class, $row);
        $this->assertEquals($rowId, $row->id);
        $this->assertEquals('Test Name', $row->name);
    }
}
