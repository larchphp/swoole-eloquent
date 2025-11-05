<?php

declare(strict_types=1);

namespace SwooleEloquent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SwooleEloquent\ORM\AsyncModel;
use SwooleEloquent\Connection\SwoolePostgresConnection;
use Mockery as m;

class AsyncModelTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testGetTableReturnsTableName(): void
    {
        $model = new class extends AsyncModel {
            protected ?string $table = 'test_table';
        };

        $this->assertEquals('test_table', $model->getTable());
    }

    public function testGetTableAutoGeneratesFromClassName(): void
    {
        $model = new class extends AsyncModel {
            // Не устанавливаем table
        };

        // Должен быть основан на имени класса
        $tableName = $model->getTable();
        $this->assertIsString($tableName);
    }

    public function testGetKeyNameReturnsDefaultPrimaryKey(): void
    {
        $model = new class extends AsyncModel {};

        $this->assertEquals('id', $model->getKeyName());
    }

    public function testSetAttributeSetsValue(): void
    {
        $model = new class extends AsyncModel {};
        $model->setAttribute('name', 'John');

        $this->assertEquals('John', $model->getAttribute('name'));
    }

    public function testMagicGetterAndSetter(): void
    {
        $model = new class extends AsyncModel {};
        $model->name = 'Jane';

        $this->assertEquals('Jane', $model->name);
    }

    public function testFillWithFillableAttributes(): void
    {
        $model = new class extends AsyncModel {
            protected array $fillable = ['name', 'email'];
        };

        $model->fill([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret', // Не в fillable, должен игнорироваться
        ]);

        $this->assertEquals('John', $model->name);
        $this->assertEquals('john@example.com', $model->email);
        $this->assertNull($model->password);
    }

    public function testGetDirtyReturnsChangedAttributes(): void
    {
        $model = new class extends AsyncModel {};
        $model->setAttribute('name', 'Original');
        $model->syncOriginal();

        $model->setAttribute('name', 'Changed');
        $dirty = $model->getDirty();

        $this->assertArrayHasKey('name', $dirty);
        $this->assertEquals('Changed', $dirty['name']);
    }

    public function testGetDirtyReturnsEmptyWhenNoChanges(): void
    {
        $model = new class extends AsyncModel {};
        $model->setAttribute('name', 'Original');
        $model->syncOriginal();

        $dirty = $model->getDirty();

        $this->assertEmpty($dirty);
    }

    public function testToArrayReturnsAttributes(): void
    {
        $model = new class extends AsyncModel {};
        $model->name = 'John';
        $model->email = 'john@example.com';

        $array = $model->toArray();

        $this->assertEquals([
            'name' => 'John',
            'email' => 'john@example.com',
        ], $array);
    }

    public function testToArrayRespectsHiddenAttributes(): void
    {
        $model = new class extends AsyncModel {
            protected array $hidden = ['password'];
        };

        $model->name = 'John';
        $model->password = 'secret';

        $array = $model->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('password', $array);
    }

    public function testToArrayRespectsVisibleAttributes(): void
    {
        $model = new class extends AsyncModel {
            protected array $visible = ['name'];
        };

        $model->name = 'John';
        $model->email = 'john@example.com';

        $array = $model->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('email', $array);
    }

    public function testToJsonReturnsJsonString(): void
    {
        $model = new class extends AsyncModel {};
        $model->name = 'John';

        $json = $model->toJson();

        $this->assertJson($json);
        $this->assertEquals('{"name":"John"}', $json);
    }

    public function testMagicIssetReturnsTrue(): void
    {
        $model = new class extends AsyncModel {};
        $model->name = 'John';

        $this->assertTrue(isset($model->name));
    }

    public function testMagicIssetReturnsFalse(): void
    {
        $model = new class extends AsyncModel {};

        $this->assertFalse(isset($model->name));
    }

    public function testMagicUnset(): void
    {
        $model = new class extends AsyncModel {};
        $model->name = 'John';

        unset($model->name);

        $this->assertFalse(isset($model->name));
    }

    public function testIsFillableWithFillable(): void
    {
        $model = new class extends AsyncModel {
            protected array $fillable = ['name', 'email'];

            public function testIsFillable(string $key): bool
            {
                return $this->isFillable($key);
            }
        };

        $this->assertTrue($model->testIsFillable('name'));
        $this->assertTrue($model->testIsFillable('email'));
        $this->assertFalse($model->testIsFillable('password'));
    }

    public function testIsFillableWithGuarded(): void
    {
        $model = new class extends AsyncModel {
            protected array $fillable = [];
            protected array $guarded = ['id'];

            public function testIsFillable(string $key): bool
            {
                return $this->isFillable($key);
            }
        };

        $this->assertFalse($model->testIsFillable('id'));
    }

    public function testIsFillableWithGuardedAll(): void
    {
        $model = new class extends AsyncModel {
            protected array $fillable = [];
            protected array $guarded = ['*'];

            public function testIsFillable(string $key): bool
            {
                return $this->isFillable($key);
            }
        };

        $this->assertFalse($model->testIsFillable('name'));
        $this->assertFalse($model->testIsFillable('email'));
    }
}
