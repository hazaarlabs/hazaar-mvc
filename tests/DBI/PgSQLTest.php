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
        $this->db->exec('CREATE TABLE test_table (id INT PRIMARY KEY, name TEXT, stored BOOLEAN DEFAULT TRUE)');
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
    }

    public function testModelInsert(): void
    {
        $sql = 'INSERT INTO "public"."test_table" (id, name) VALUES (1234, \'Test Name\') RETURNING stored';
        $data = [
            'id' => 1234,
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
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $result = $this->db->table('test_table')->find(['id' => 1234]);
        $model = $result->fetchModel(DBITestModel::class);
        $this->assertInstanceOf(DBITestModel::class, $model);
        $this->assertEquals($data['name'], $model->name);
        $this->assertEquals($data['id'], $model->id);
        $this->assertTrue($model->stored); // default value
    }

    public function testInsertSelect(): void
    {
        $sql = 'SELECT * FROM "public"."test_table" WHERE id = 1234';
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
            'stored' => true,
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $statement = $this->db->table('test_table')->find(['id' => 1234]);
        $this->assertEquals($sql, (string) $statement);
        $this->assertEquals($data, $statement->fetch());
    }

    public function testSelectRow(): void
    {
        $sql = 'SELECT * FROM "public"."test_table" WHERE id = 1234';
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
        ];
        $this->assertEquals(1, $this->db->table('test_table')->insert($data));
        $statement = $this->db->table('test_table')->find(['id' => 1234]);
        $this->assertEquals($sql, (string) $statement);
        $row = $statement->row();
        $this->assertInstanceOf(Row::class, $row);
        $this->assertEquals(1234, $row->id);
        $this->assertEquals('Test Name', $row->name);
    }
}
