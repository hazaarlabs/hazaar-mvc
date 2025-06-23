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
        $sql = 'SELECT id, name FROM "test_table" WHERE id = :id0 ORDER BY id ASC NULLS LAST LIMIT 10 OFFSET 5;';
        $this->assertEquals($sql, (string) $query);
        $values = $query->getCriteriaValues();
        $this->assertEquals(['id0' => 1], $values);
    }

    public function testAdvancedSELECT(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['id' => 1, 'name' => 'test'])
            ->order('id', SORT_ASC)
            ->limit(10)
            ->offset(5)
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE id = :id0 AND name = :name0 ORDER BY id ASC NULLS LAST LIMIT 10 OFFSET 5;';
        $this->assertEquals($sql, (string) $query);
        $values = $query->getCriteriaValues();
        $this->assertEquals(['id0' => 1, 'name0' => 'test'], $values);
    }

    public function testBasicINSERT(): void
    {
        $query = new SQL();
        $query->insert(['id' => 1, 'name' => 'test'])
            ->table('test_table')
        ;
        $sql = 'INSERT INTO "test_table" (id, name) VALUES (:id0, :name0);';
        $this->assertEquals($sql, (string) $query);
    }

    public function testBasicUPDATE(): void
    {
        $query = new SQL();
        $query->update(['active' => true, 'name' => 'test'])
            ->table('test_table')
            ->where(['id' => 1])
        ;
        $sql = 'UPDATE "test_table" SET active = :active0, name = :name0 WHERE id = :id0;';
        $this->assertEquals($sql, (string) $query);
    }

    public function testBasicDELETE(): void
    {
        $query = new SQL();
        $query->delete()->from('test_table')->where(['id' => 1]);
        $sql = 'DELETE FROM "test_table" WHERE id = :id0;';
        $this->assertEquals($sql, (string) $query);
    }

    public function testBasicTRUNCATE(): void
    {
        $query = new SQL();
        $query->truncate()->table('test_table');
        $sql = 'TRUNCATE TABLE "test_table";';
        $this->assertEquals($sql, (string) $query);
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
        $sql = 'SELECT id, name FROM "test_table" INNER JOIN "other_table" ON test_table.id = other_table.id WHERE id = :id0 ORDER BY id ASC NULLS LAST LIMIT 10 OFFSET 5;';
        $this->assertEquals($sql, (string) $query);
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
        $this->assertEquals($sql, (string) $query->toString());
    }

    public function testSELECTWithGreaterThan(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['id' => ['$gt' => 8]])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE id > :id0';
        $this->assertEquals($sql, (string) $query->toString());
    }

    public function testSELECTWithNotEquals(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['name' => ['$ne' => 'test']])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE name != :name0';
        $this->assertEquals($sql, (string) $query->toString());
    }

    public function testSELECTWithNestedGreaterThan(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['$and' => [
                ['$or' => [
                    ['id' => ['$lt' => 8]],
                    ['id' => ['$gt' => 2]],
                ]],
                ['name' => ['$ne' => 'test']],
            ]])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE ( id < :id0 OR id > :id1 ) AND name != :name0';
        $this->assertEquals($sql, (string) $query->toString());
    }

    public function testSELECTWithOrOfSameColumn(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['$or' => [
                ['id' => 8],
                ['id' => 2],
            ]])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE id = :id0 OR id = :id1';
        $this->assertEquals($sql, (string) $query->toString());
    }

    public function testSELECTColumnThatIsNull(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['$null' => 'parent'])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE "parent" IS NULL';
        $this->assertEquals($sql, (string) $query->toString());
    }

    public function testSELECTColumnThatIsNotNull(): void
    {
        $query = new SQL();
        $query->select(['id', 'name'])
            ->from('test_table')
            ->where(['$notnull' => 'parent'])
        ;
        $sql = 'SELECT id, name FROM "test_table" WHERE "parent" IS NOT NULL';
        $this->assertEquals($sql, (string) $query->toString());
    }

    public function testSELECTColumnWithTableAlias(): void
    {
        $query = new SQL();
        $query->select(['t.id', 't.name'])
            ->from('test_table', 't')
            ->where(['t.id' => 1])
        ;
        $sql = 'SELECT t.id, t.name FROM "test_table" AS t WHERE t.id = :t_id0';
        $this->assertEquals($sql, (string) $query->toString());
    }
}
