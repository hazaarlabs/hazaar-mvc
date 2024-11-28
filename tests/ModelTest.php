<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Model;
use Hazaar\Model\Email;
use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Exception\UnsetPropertyException;
use PHPUnit\Framework\TestCase;

class AgeModel extends Model
{
    protected int $years = 0;
    protected int $dob;

    public function construct(array &$data): void
    {
        $this->defineEventHook('read', 'years', function ($value) {
            return age($this->dob);
        });
    }
}
class TestModel extends Model
{
    protected int $id;
    protected string $name;
    protected ?string $email = null;
    protected ?string $description = null;
    protected int $counter = 0;
    protected TestModel $child;
    protected AgeModel $age;
    protected bool $isActive = false;

    /**
     * @var array<int,string>
     */
    protected array $categories = [];
    protected string $date;

    public function construct(array &$data): void
    {
        $this->defineEventHook('read', 'name', function ($value) {
            return $value.'!!!';
        });
        $this->defineEventHook('write', 'id', function ($value) {
            return (int) $value;
        });
        $this->defineRule('min', 'counter', 1);
        $this->defineRule('max', 'counter', 10);
        $this->defineRule('required', ['id', 'name']);
        $this->defineRule('filter', 'email', FILTER_VALIDATE_EMAIL);
        $this->defineRule('pad', 'description', 8);
        $this->defineRule('contains', 'categories', 'id');
        // $this->defineRule('format', 'phrase', 'The %2$s contains %1$d monkeys');
    }
}
class DynamicModel extends Model
{
    protected int $number;

    public function construct(array &$data, ?string $name = null, ?int $number = null): void
    {
        $this->defineProperty('string', 'name', $name);
        $data['number'] = $number;
    }
}

/**
 * @internal
 */
class ModelTest extends TestCase
{
    /**
     * @var array<string,mixed>
     */
    private array $data = [
        'id' => 1234,
        'name' => 'John Doe',
        'email' => null,
        'counter' => 0,
        'child' => ['name' => 'George Doe'],
        'age' => ['dob' => 0],
        'isActive' => true,
    ];

    public function testNewModel(): void
    {
        $model = new TestModel($this->data);
        $this->assertEquals($this->data['id'], $model->id);
        $this->assertEquals($this->data['name'].'!!!', $model->name);
        $this->assertEquals($this->data['email'], $model->email);
    }

    public function testJSONModel(): void
    {
        $model = TestModel::fromJSONString(json_encode($this->data));
        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertInstanceOf('Hazaar\Model', $model);
        $this->assertEquals($this->data['id'], $model->id);
        $this->assertEquals($this->data['name'].'!!!', $model->name);
        $this->assertEquals($this->data['email'], $model->email);
    }

    public function testDataTypes(): void
    {
        $model = new TestModel($this->data);
        $this->assertIsInt($model->id);
        $this->assertIsString($model->name);
        $this->assertNull($model->email);
        $model->id = '1234';
        $this->assertIsInt($model->id);
        $model->id = 123.4;
        $this->assertIsInt($model->id);
        $this->assertIsBool($model->isActive);
        $model->isActive = 'true';
        $this->assertEquals(true, $model->isActive);
        $model->isActive = 'false';
        $this->assertEquals(false, $model->isActive);
    }

    public function testEventHooks(): void
    {
        $model = $model = new TestModel($this->data);
        $model->defineEventHook('write', 'counter', function ($value) {
            return $value + 1;
        });
        $this->assertEquals(0, $model->counter);
        $model->counter = 100;
        $this->assertEquals(11, $model->counter);
        $model->age->dob = strtotime('1978-12-13');
        $this->assertEquals(45, $model->age->years);
    }

    public function testMinMaxRules(): void
    {
        $model = $model = new TestModel($this->data);
        $model->counter = 0;
        $this->assertEquals(1, $model->counter);
        $model->counter = 100;
        $this->assertEquals(10, $model->counter);
    }

    public function testRequiredRule(): void
    {
        $model = new TestModel($this->data);
        $this->expectException(PropertyValidationException::class);
        $model->name = null;
    }

    public function testEmailRule(): void
    {
        $model = new TestModel($this->data);
        $model->email = 'jonny@doe.com';
        $this->assertEquals('jonny@doe.com', $model->email);
        $this->expectException(PropertyValidationException::class);
        $model->email = 'john@doe';
    }

    public function testEmailModel(): void
    {
        $model = new Email([
            'address' => 'john@doe.com',
            'name' => 'John Doe',
        ]);
        $this->assertEquals('John Doe', $model->name);
    }

    public function testStringPadding(): void
    {
        $model = new TestModel($this->data);
        $model->description = '1234';
        $this->assertNotEquals(strlen($model->description), 4);
        $this->assertEquals(strlen($model->description), 8);
    }

    public function testArrayContains(): void
    {
        $model = new TestModel($this->data);
        $model->categories = ['id', 'name', 'description'];
        $this->assertEquals(3, count($model->categories));
        $this->expectException(PropertyValidationException::class);
        $model->categories = ['name', 'description'];
    }

    public function testPropertiesMissing(): void
    {
        $model = new TestModel([
            'name' => 'John Doe',
            'test' => true,
        ]);

        $this->assertEquals('John Doe!!!', $model->name);
        $this->assertTrue(isset($model->name));
        $this->assertFalse(isset($model->test));
        $this->expectException(UnsetPropertyException::class);
        unset($model->name);
    }

    public function testSerialise(): void
    {
        $model = new TestModel($this->data);
        $string = serialize($model);
        $this->assertEquals(424, strlen($string));
        $newModel = unserialize($string);
        $this->assertInstanceOf(TestModel::class, $newModel);
        $array = $newModel->toArray(true);
        $this->assertArrayNotHasKey('email', $array);
    }

    public function testHelper(): void
    {
        $model = new TestModel($this->data);
        $model->set('name', 'Jane Doe');
        $this->assertEquals('Jane Doe!!!', ake($model, 'name'));
    }

    public function testDynamicProperties(): void
    {
        unset($this->data['name']);
        $model = new DynamicModel($this->data, 'no name', 1313);
        $this->assertEquals('no name', $model->get('name'));
        $model->set('name', 'This is a test');
        $this->assertEquals('This is a test', $model->get('name'));
        $this->assertEquals('This is a test', $model->name);
        $this->assertTrue(isset($model->name));
        unset($model->name);
        $this->assertFalse(isset($model->name));
        $this->assertEquals(1313, $model->number);
    }
}
