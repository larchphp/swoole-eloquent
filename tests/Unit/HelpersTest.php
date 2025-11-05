<?php

declare(strict_types=1);

namespace SwooleEloquent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SwooleEloquent\Support\Helpers;

class HelpersTest extends TestCase
{
    public function testPgToPHPBoolean(): void
    {
        $this->assertTrue(Helpers::pgToPHP('t', 'bool'));
        $this->assertTrue(Helpers::pgToPHP('true', 'boolean'));
        $this->assertFalse(Helpers::pgToPHP('f', 'bool'));
        $this->assertFalse(Helpers::pgToPHP('false', 'boolean'));
    }

    public function testPgToPHPInteger(): void
    {
        $this->assertSame(42, Helpers::pgToPHP('42', 'int'));
        $this->assertSame(100, Helpers::pgToPHP('100', 'integer'));
    }

    public function testPgToPHPFloat(): void
    {
        $this->assertSame(3.14, Helpers::pgToPHP('3.14', 'float'));
        $this->assertSame(2.5, Helpers::pgToPHP('2.5', 'numeric'));
    }

    public function testPgToPHPNull(): void
    {
        $this->assertNull(Helpers::pgToPHP(null, 'any'));
    }

    public function testPhpToPGBoolean(): void
    {
        $this->assertEquals('t', Helpers::phpToPG(true));
        $this->assertEquals('f', Helpers::phpToPG(false));
    }

    public function testPhpToPGArray(): void
    {
        $result = Helpers::phpToPG(['a', 'b', 'c']);
        $this->assertJson($result);
        $this->assertEquals('["a","b","c"]', $result);
    }

    public function testPhpToPGDateTime(): void
    {
        $date = new \DateTime('2024-01-15 10:30:45');
        $result = Helpers::phpToPG($date);

        $this->assertEquals('2024-01-15 10:30:45', $result);
    }

    public function testPhpToPGNull(): void
    {
        $this->assertNull(Helpers::phpToPG(null));
    }

    public function testParsePostgresArray(): void
    {
        $result = Helpers::parsePostgresArray('{a,b,c}');

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testParsePostgresArrayEmpty(): void
    {
        $result = Helpers::parsePostgresArray('{}');

        $this->assertEquals([], $result);
    }

    public function testArrayGet(): void
    {
        $array = [
            'name' => 'John',
            'user' => [
                'profile' => [
                    'age' => 30,
                ],
            ],
        ];

        $this->assertEquals('John', Helpers::arrayGet($array, 'name'));
        $this->assertEquals(30, Helpers::arrayGet($array, 'user.profile.age'));
    }

    public function testArrayGetWithDefault(): void
    {
        $array = ['name' => 'John'];

        $this->assertEquals('default', Helpers::arrayGet($array, 'nonexistent', 'default'));
        $this->assertEquals('default', Helpers::arrayGet($array, 'user.profile.age', 'default'));
    }

    public function testCamelCase(): void
    {
        $this->assertEquals('userName', Helpers::camelCase('user_name'));
        $this->assertEquals('firstName', Helpers::camelCase('first_name'));
        $this->assertEquals('id', Helpers::camelCase('id'));
    }

    public function testSnakeCase(): void
    {
        $this->assertEquals('user_name', Helpers::snakeCase('userName'));
        $this->assertEquals('first_name', Helpers::snakeCase('firstName'));
        $this->assertEquals('id', Helpers::snakeCase('id'));
    }

    public function testClassBasename(): void
    {
        $this->assertEquals('HelpersTest', Helpers::classBasename($this));
        $this->assertEquals('Helpers', Helpers::classBasename(Helpers::class));
        $this->assertEquals('TestClass', Helpers::classBasename('App\Models\TestClass'));
    }

    public function testFormatBytes(): void
    {
        $this->assertEquals('0 B', Helpers::formatBytes(0));
        $this->assertEquals('1 KB', Helpers::formatBytes(1024));
        $this->assertEquals('1 MB', Helpers::formatBytes(1024 * 1024));
        $this->assertEquals('1 GB', Helpers::formatBytes(1024 * 1024 * 1024));
    }

    public function testIsValidEmail(): void
    {
        $this->assertTrue(Helpers::isValidEmail('test@example.com'));
        $this->assertTrue(Helpers::isValidEmail('user.name@example.co.uk'));
        $this->assertFalse(Helpers::isValidEmail('invalid'));
        $this->assertFalse(Helpers::isValidEmail('invalid@'));
        $this->assertFalse(Helpers::isValidEmail('@invalid.com'));
    }

    public function testUuid(): void
    {
        $uuid = Helpers::uuid();

        $this->assertIsString($uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testUuidIsUnique(): void
    {
        $uuid1 = Helpers::uuid();
        $uuid2 = Helpers::uuid();

        $this->assertNotEquals($uuid1, $uuid2);
    }

    public function testRetrySuccess(): void
    {
        $attempts = 0;

        $result = Helpers::retry(function () use (&$attempts) {
            $attempts++;
            return 'success';
        }, 3, 10);

        $this->assertEquals('success', $result);
        $this->assertEquals(1, $attempts);
    }

    public function testRetryFailsThenSucceeds(): void
    {
        $attempts = 0;

        $result = Helpers::retry(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('Temporary failure');
            }
            return 'success';
        }, 5, 1);

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    public function testRetryFailsAfterMaxAttempts(): void
    {
        $this->expectException(\RuntimeException::class);

        Helpers::retry(function () {
            throw new \RuntimeException('Permanent failure');
        }, 3, 1);
    }

    public function testBenchmark(): void
    {
        $result = Helpers::benchmark(function () {
            usleep(1000); // 1ms
            return 'done';
        }, 'test');

        $this->assertEquals('done', $result);
    }

    public function testLogDoesNotThrow(): void
    {
        // Просто проверяем, что метод не выбрасывает исключение
        Helpers::log('Test message');
        Helpers::log('Test with context', ['key' => 'value']);
        Helpers::log('Test level', [], 'debug');

        $this->assertTrue(true);
    }
}
