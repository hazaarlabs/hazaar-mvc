<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\DBI\Adapter;
use Hazaar\DBI\QueryBuilder\SQL;
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
class DBITest extends TestCase
{
    /**
     * @var array<string,string>
     */
    private array $config = [
        'driver' => 'pgsql',
        'host' => 'localhost',
        'user' => 'postgres',
        'password' => 'pgmock',
    ];

    public function setUp(): void
    {
        $db = new Adapter($this->config);
        $db->exec('DROP TABLE IF EXISTS test_table');
        $db->exec('CREATE TABLE test_table (id INT PRIMARY KEY, name TEXT, stored BOOLEAN DEFAULT TRUE)');
    }

    public function testDatabaseConfig(): void
    {
        $db = new Adapter($this->config);
        $this->assertEquals('pgsql', $db->config['driver']);
        $this->assertEquals([['name' => 'test_table', 'schema' => 'public']], $db->listTables());
    }

    public function testSQLGeneratorSELECT(): void
    {
        $db = new Adapter($this->config);
        $sql = 'SELECT id, name FROM "public"."test" WHERE id = 1';
        $this->assertEquals($sql, (string) $db->table('test')->select(['id', 'name'])->where(['id' => 1]));
        $this->assertEquals($sql, (string) $db->table('test')->find(['id' => 1], ['id', 'name']));
    }

    public function testSQLGeneratorJOIN(): void
    {
        $db = new Adapter($this->config);
        $sql = 'SELECT id, name FROM "public"."test" "t1" INNER JOIN test2 t2 ON t1.id = t2.id WHERE id = 1';
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
        $this->assertEquals($sql, $db->lastQueryString());
    }

    public function testSQLGeneratorUPDATE(): void
    {
        $db = new Adapter($this->config);
        $sql = 'UPDATE "public"."test_table" SET name = \'updated\' WHERE id = 1';
        $this->assertEquals(1, $db->table('test_table')->insert(['id' => 1, 'name' => 'test']));
        $this->assertEquals(1, $db->table('test_table')->update(['name' => 'updated'], ['id' => 1]));
        $this->assertEquals($sql, $db->lastQueryString());
    }

    public function testSQLGeneratorDELETE(): void
    {
        $db = new Adapter($this->config);
        $sql = 'DELETE FROM "public"."test_table" WHERE (id = 1 OR id = 2)';
        $this->assertEquals(1, $db->table('test_table')->insert(['id' => 1, 'name' => 'test']));
        $this->assertEquals(1, $db->table('test_table')->insert(['id' => 2, 'name' => 'test']));
        $this->assertEquals(2, $db->table('test_table')->delete(['$or' => [['id' => 1], ['id' => 2]]]));
        $this->assertEquals($sql, $db->lastQueryString());
    }

    public function testModelInsert(): void
    {
        $db = new Adapter($this->config);
        $sql = 'INSERT INTO "public"."test_table" (id, name) VALUES (1234, \'Test Name\') RETURNING stored';
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
            'extra' => 'extra data that will not be inserted',
        ];
        $model = new DBITestModel($data);
        $this->assertInstanceOf($model::class, $db->table('test_table')->insertModel($model));
        $this->assertTrue($model->stored);
        $this->assertEquals($sql, $db->lastQueryString());
    }

    public function testInsertSelect(): void
    {
        $db = new Adapter($this->config);
        $sql = 'SELECT * FROM "public"."test_table" WHERE id = 1234';
        $data = [
            'id' => 1234,
            'name' => 'Test Name',
            'stored' => true,
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

    public function testQueryBuilderSELECT(): void
    {
        $sql = new SQL();
        $sql->select('id', 'identity')
            ->from('test_table', 'tt')
            ->join('users', 'u.id = tt.user_id', 'u')
            ->where(['ip' => '127.0.0.1'])
        ;
        $this->assertEquals('SELECT id, identity FROM "test_table" AS tt INNER JOIN users u ON u.id = tt.user_id WHERE ip = \'127.0.0.1\'', $sql->toString());
    }

    public function testQueryBuilderINSERT(): void
    {
        $sql = new SQL();
        $string = $sql->insert('test_table', ['id' => 1234, 'name' => 'Test Name']);
        $this->assertEquals('INSERT INTO "test_table" (id, name) VALUES (1234, \'Test Name\')', $string);
    }

    public function testQueryBuilderUPDATE(): void
    {
        $sql = new SQL();
        $string = $sql->update('test_table', ['name' => 'Test Name'], ['id' => 1234]);
        $this->assertEquals('UPDATE "test_table" SET name = \'Test Name\' WHERE id = 1234', $string);
    }

    public function testQueryBuilderDELETE(): void
    {
        $sql = new SQL();
        $string = $sql->delete('test_table', ['id' => 1234]);
        $this->assertEquals('DELETE FROM "test_table" WHERE id = 1234', $string);
    }
}
