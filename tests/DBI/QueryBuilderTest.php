<?php

declare(strict_types=1);

namespace Hazaar\Tests\DBI;

use Hazaar\DBI\QueryBuilder\SQL;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QueryBuilderTest extends TestCase
{
    public function testBasicSELECT(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['id' => 1])
            ->order('id', SORT_ASC)
            ->limit(10)
            ->offset(5)
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE id = 1 ORDER BY id ASC NULLS LAST LIMIT 10 OFFSET 5';
        $this->assertEquals($sql, $query->toString());
    }

    public function testBasicINSERT(): void
    {
        $query = new SQL();
        $sql = 'INSERT INTO "test_table" (id, name) VALUES (1, \'test\')';
        $this->assertEquals($sql, $query->insert('test_table', ['id' => 1, 'name' => 'test']));
    }

    public function testBasicUPDATE(): void
    {
        $query = new SQL();
        $sql = 'UPDATE "test_table" SET id = 1, name = \'test\' WHERE id = 1';
        $this->assertEquals($sql, $query->update('test_table', ['id' => 1, 'name' => 'test'], ['id' => 1]));
    }

    public function testBasicDELETE(): void
    {
        $query = new SQL();
        $sql = 'DELETE FROM "test_table" WHERE id = 1';
        $this->assertEquals($sql, $query->delete('test_table', ['id' => 1]));
    }

    public function testBasicTRUNCATE(): void
    {
        $query = new SQL();
        $sql = 'TRUNCATE TABLE "test_table"';
        $this->assertEquals($sql, $query->truncate('test_table'));
    }

    public function testSELECTWithJoin(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->join('other_table', 'test_table.id = other_table.id')
            ->where(['id' => 1])
            ->order('id', SORT_ASC)
            ->limit(10)
            ->offset(5)
        ;
        $sql = 'SELECT id, name FROM "test_table" INNER JOIN "other_table" ON test_table.id = other_table.id WHERE id = 1 ORDER BY id ASC NULLS LAST LIMIT 10 OFFSET 5';
        $this->assertEquals($sql, $query->toString());
    }

    public function testSELECTWithGroupBy(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->group('id')
            ->having('COUNT(id) > 1')
        ;
        $sql = 'SELECT id, name FROM "test_table" GROUP BY id HAVING ( COUNT(id) > 1 )';
        $this->assertEquals($sql, $query->toString());
    }

    public function testSELECTWithGreaterThan(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['id' => ['$gt' => 8]])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE id > 8';
        $this->assertEquals($sql, $query->toString());
    }

    public function testSELECTWithNotEquals(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['name' => ['$ne' => 'test']])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE name != \'test\'';
        $this->assertEquals($sql, $query->toString());
    }

    public function testSELECTWithNestedGreaterThan(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['$or' => [
                ['id' => ['$gt' => 8]],
                ['name' => ['$ne' => 'test']],
            ]])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE ( id > 8 OR name != \'test\' )';
        $this->assertEquals($sql, $query->toString());
    }
}
