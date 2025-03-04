<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Model;
use Hazaar\Model\Attribute\Contains;
use Hazaar\Model\Attribute\Filter;
use Hazaar\Model\Attribute\Format;
use Hazaar\Model\Attribute\Max;
use Hazaar\Model\Attribute\MaxLength;
use Hazaar\Model\Attribute\Min;
use Hazaar\Model\Attribute\MinLength;
use Hazaar\Model\Attribute\Pad;
use Hazaar\Model\Attribute\Required;
use Hazaar\Model\Attribute\Trim;
use Hazaar\Model\Email;
use PHPUnit\Framework\TestCase;

class AgeModel extends Model
{
    protected int $years = 0;
    protected int $dob;

    public function construct(array &$data): void
    {
        $this->defineEventHook('get', 'years', function ($value) {
            return age($this->dob);
        });
    }
}
class TestModel extends Model
{
    #[Required]
    protected int $id;
    #[Required]
    #[Trim()]
    protected string $name;
    #[Filter(FILTER_VALIDATE_EMAIL)]
    protected string $email;
    #[Contains('ID')]
    #[Pad(8)]
    protected ?string $description = null;
    #[Min(1)]
    #[Max(10)]
    protected int $counter = 1;
    protected TestModel $child;
    protected AgeModel $age;
    protected bool $isActive = false;

    #[MinLength(10)]
    #[MaxLength(20)]
    protected string $brief;

    /**
     * @var array<int,string>
     */
    #[Contains('id')]
    protected array $categories = [];
    protected string $date;

    #[Format('Monkey: %b')]
    protected string $phrase;

    public function construct(array &$data): void
    {
        $this->defineEventHook('get', 'name', function ($value) {
            return $value.'!!!';
        });
        $this->defineEventHook('set', 'id', function ($value) {
            return (int) $value;
        });
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
        'email' => 'test@example.com',
        'description' => 'ID:1234',
        'counter' => 0,
        'child' => ['name' => 'George Doe', 'id' => 1235],
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
        $this->assertIsString($model->email);
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
        $model->defineEventHook('get', 'counter', function ($value) {
            return $value + 1;
        });
        $this->assertEquals(2, $model->counter);
        $model->counter = 100;
        $this->assertEquals(10, $model->counter);
        $model->age->dob = strtotime('1978-12-13');
        $this->assertEquals(46, $model->age->years);
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
        $model->name = '';
        $this->assertEquals('John Doe!!!', $model->name);
    }

    public function testEmailRule(): void
    {
        $model = new TestModel($this->data);
        $model->email = 'jonny@doe.com';
        $this->assertEquals('jonny@doe.com', $model->email);
        $model->email = 'john@doe';
        $this->assertEquals('jonny@doe.com', $model->email);
        $model->email = 'bad email';
        $this->assertEquals('jonny@doe.com', $model->email);
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
        $this->assertNotEquals(4, strlen($model->description));
        $this->assertEquals(8, strlen($model->description));
    }

    public function testArrayContains(): void
    {
        $model = new TestModel($this->data);
        $model->categories = ['id', 'name', 'description'];
        $this->assertEquals(3, count($model->categories));
        $model->categories = ['name', 'description'];
        $this->assertEquals(3, count($model->categories));
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
        unset($model->name);
        $this->assertFalse(isset($model->name));
    }

    public function testSerialise(): void
    {
        $model = new TestModel($this->data);
        $string = serialize($model);
        $this->assertEquals(516, strlen($string));
        $newModel = unserialize($string);
        $this->assertInstanceOf(TestModel::class, $newModel);
        unset($newModel->email);
        $array = $newModel->toArray();
        $this->assertArrayNotHasKey('email', $array);
    }

    public function testHelper(): void
    {
        $model = new TestModel($this->data);
        $model->set('name', 'Jane Doe');
        $this->assertEquals('Jane Doe!!!', $model->name);
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

    public function testPropertyLengthRules(): void
    {
        $model = new TestModel($this->data);
        $model->brief = str_repeat('x', 15);
        $this->assertGreaterThanOrEqual(15, strlen($model->brief));
        $model->brief = str_repeat('x', 5); // Too short for MinLength rule so will not be set
        $this->assertGreaterThanOrEqual(15, strlen($model->brief));
        $model->brief = str_repeat('x', 25); // Too long for MaxLength rule so will be truncated
        $this->assertLessThanOrEqual(20, strlen($model->brief));
    }

    public function testPropertyFormat(): void
    {
        $model = new TestModel($this->data);
        $model->phrase = 123;
        $this->assertEquals('Monkey: 1111011', $model->phrase);
    }

    public function testPropertyTrim(): void
    {
        $model = new TestModel($this->data);
        $model->name = '  John Doe  ';
        $this->assertEquals('John Doe!!!', $model->name);
    }
}
