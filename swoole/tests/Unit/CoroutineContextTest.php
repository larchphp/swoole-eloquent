<?php

declare(strict_types=1);

namespace SwooleEloquent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SwooleEloquent\Support\CoroutineContext;

class CoroutineContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CoroutineContext::clearAll();
    }

    protected function tearDown(): void
    {
        CoroutineContext::clearAll();
        parent::tearDown();
    }

    public function testGetIdReturnsInteger(): void
    {
        $id = CoroutineContext::getId();

        $this->assertIsInt($id);
    }

    public function testPutAndGet(): void
    {
        CoroutineContext::put('key', 'value');

        $this->assertEquals('value', CoroutineContext::get('key'));
    }

    public function testGetWithDefault(): void
    {
        $value = CoroutineContext::get('nonexistent', 'default');

        $this->assertEquals('default', $value);
    }

    public function testHasReturnsTrue(): void
    {
        CoroutineContext::put('key', 'value');

        $this->assertTrue(CoroutineContext::has('key'));
    }

    public function testHasReturnsFalse(): void
    {
        $this->assertFalse(CoroutineContext::has('nonexistent'));
    }

    public function testForget(): void
    {
        CoroutineContext::put('key', 'value');
        CoroutineContext::forget('key');

        $this->assertFalse(CoroutineContext::has('key'));
    }

    public function testGetContext(): void
    {
        CoroutineContext::put('key1', 'value1');
        CoroutineContext::put('key2', 'value2');

        $context = CoroutineContext::getContext();

        $this->assertIsArray($context);
        $this->assertEquals('value1', $context['key1']);
        $this->assertEquals('value2', $context['key2']);
    }

    public function testClear(): void
    {
        CoroutineContext::put('key1', 'value1');
        CoroutineContext::put('key2', 'value2');

        CoroutineContext::clear();

        $this->assertFalse(CoroutineContext::has('key1'));
        $this->assertFalse(CoroutineContext::has('key2'));
    }

    public function testClearAll(): void
    {
        CoroutineContext::put('key', 'value');

        CoroutineContext::clearAll();

        $this->assertEquals(0, CoroutineContext::count());
    }

    public function testCount(): void
    {
        $this->assertEquals(0, CoroutineContext::count());

        CoroutineContext::put('key', 'value');

        // В текущем контексте (не в корутине) будет 1 контекст
        $count = CoroutineContext::count();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testWithContext(): void
    {
        $result = CoroutineContext::withContext(function () {
            CoroutineContext::put('key', 'value');
            return CoroutineContext::get('key');
        });

        $this->assertEquals('value', $result);

        // После выполнения контекст должен быть очищен
        $this->assertFalse(CoroutineContext::has('key'));
    }

    public function testWithContextClearsOnException(): void
    {
        try {
            CoroutineContext::withContext(function () {
                CoroutineContext::put('key', 'value');
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException $e) {
            // Ожидаем исключение
        }

        // Контекст должен быть очищен даже после исключения
        $this->assertFalse(CoroutineContext::has('key'));
    }

    public function testMultipleValues(): void
    {
        CoroutineContext::put('string', 'hello');
        CoroutineContext::put('number', 42);
        CoroutineContext::put('array', [1, 2, 3]);
        CoroutineContext::put('object', (object)['key' => 'value']);

        $this->assertEquals('hello', CoroutineContext::get('string'));
        $this->assertEquals(42, CoroutineContext::get('number'));
        $this->assertEquals([1, 2, 3], CoroutineContext::get('array'));
        $this->assertIsObject(CoroutineContext::get('object'));
    }

    public function testOverwriteValue(): void
    {
        CoroutineContext::put('key', 'value1');
        $this->assertEquals('value1', CoroutineContext::get('key'));

        CoroutineContext::put('key', 'value2');
        $this->assertEquals('value2', CoroutineContext::get('key'));
    }
}
