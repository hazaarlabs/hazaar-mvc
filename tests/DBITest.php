<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\Row;
use Hazaar\Model;
use PHPUnit\Framework\TestCase;

class DBITestModel extends Model
{
    protected int $id;
    protected string $name;
    protected ?string $empty = null;
}

/**
 * @internal
 */
class DBITest extends TestCase
{
    /**
     * @var array<string,string>
     */
    private array $config = ['driver' => 'dummy'];

    public function testDatabaseConfig(): void
    {
        $db = new Adapter($this->config);
        $this->assertEquals('dummy', $db->config['driver']);
        $this->assertEquals([], $db->listTables());
    }

    public function testSQLGeneratorSELECT(): void
    {
        $db = new Adapter($this->config);
        $sql = 'SELECT id, name FROM "public"."test" WHERE id = 1';
        $this->assertEquals($sql, (string) $db->table('test')->select('id', 'name')->where(['id' => 1]));
        $this->assertEquals($sql, (string) $db->table('test')->find(['id' => 1], ['id', 'name']));
    }

    public function testSQLGeneratorJOIN(): void
    {
        $db = new Adapter($this->config);
        $sql = 'SELECT id, name FROM "public"."test" AS t1 INNER JOIN test2 t2 ON t1.id = t2.id WHERE id = 1';
        $q = $db->table('test', 't1')
            ->join('test2', 't1.id = t2.id', 't2')
            ->find(['id' => 1], ['id', 'name'])
        ;
        $this->assertEquals($sql, (string) $q);

        $q = $db->table('test', 't1')
            ->join('test2', 't1.id = t2.id', 't2')
            ->find(['id' => 1], ['id', 'name'])
        ;
        $this->assertEquals($sql, (string) $q);
    }

    public function testSQLGeneratorINSERT(): void
    {
        $db = new Adapter($this->config);
        $sql = 'INSERT INTO "public"."test_table" (id, name) VALUES (1, \'test\')';
        $this->assertEquals(1, $db->table('test_table')->insert(['id' => 1, 'name' => 'test']));
        $this->assertEquals(1, $db->insert('test_table', ['id' => 1, 'name' => 'test']));
        $this->assertEquals($sql, $db->driver->lastQueryString);
    }

    public function testSQLGeneratorUPDATE(): void
    {
        $db = new Adapter($this->config);
        $sql = 'UPDATE "public"."test_table" SET name = \'test\' WHERE id = 1';
        $this->assertEquals(1, $db->table('test_table')->update(['name' => 'test'], ['id' => 1]));
        $this->assertEquals(1, $db->update('test_table', ['name' => 'test'], ['id' => 1]));
        $this->assertEquals($sql, $db->driver->lastQueryString);
    }

    public function testSQLGeneratorDELETE(): void
    {
        $db = new Adapter($this->config);
        $sql = 'DELETE FROM "public"."test_table" WHERE id = 1';
        $this->assertEquals(1, $db->table('test_table')->delete(['id' => 1]));
        $this->assertEquals(1, $db->delete('test_table', ['id' => 1]));
        $this->assertEquals($sql, $db->driver->lastQueryString);
    }

    public function testModelInsert(): void
    {
        $db = new Adapter($this->config);
        $sql = 'INSERT INTO "public"."test_table" (id, name) VALUES (1234, \'Test Name\')';
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
            'extra' => 'extra data',
        ];
        $model = new DBITestModel($data);
        $this->assertEquals(1, $db->insert('test_table', $model));
        $this->assertEquals($sql, $db->driver->lastQueryString);
    }

    public function testInsertSelect(): void
    {
        $db = new Adapter($this->config);
        $sql = 'SELECT * FROM "public"."test_table" WHERE id = 1234';
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
        ];
        $this->assertEquals(1, $db->table('test_table')->insert($data));
        $statement = $db->table('test_table')->find(['id' => 1234]);
        $this->assertEquals($sql, (string) $statement);
        $this->assertEquals($data, $statement->fetch());
    }

    public function testSelectRow(): void
    {
        $db = new Adapter($this->config);
        $sql = 'SELECT * FROM "public"."test_table" WHERE id = 1234';
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
        ];
        $this->assertEquals(1, $db->table('test_table')->insert($data));
        $statement = $db->table('test_table')->find(['id' => 1234]);
        $this->assertEquals($sql, (string) $statement);
        $row = $statement->row();
        $this->assertInstanceOf(Row::class, $row);
        $this->assertEquals(1234, $row->id);
        $this->assertEquals('Test Name', $row->name);
    }
}
