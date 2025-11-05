<?php

declare(strict_types=1);

namespace SwooleEloquent\Support;

use Swoole\Coroutine;

/**
 * Управление контекстом корутины
 * Позволяет хранить данные в рамках одной корутины
 */
class CoroutineContext
{
    /**
     * Хранилище контекста
     *
     * @var array
     */
    private static array $contexts = [];

    /**
     * Получить ID текущей корутины
     *
     * @return int
     */
    public static function getId(): int
    {
        if (!extension_loaded('swoole')) {
            // Для тестов без Swoole возвращаем фиксированный ID
            return -1;
        }

        $cid = Coroutine::getCid();

        if ($cid === -1) {
            // Не в корутине
            return -1;
        }

        return $cid;
    }

    /**
     * Сохранить значение в контексте корутины
     *
     * @param string $key
     * @param mixed $value
     */
    public static function put(string $key, mixed $value): void
    {
        $cid = self::getId();

        if (!isset(self::$contexts[$cid])) {
            self::$contexts[$cid] = [];
        }

        self::$contexts[$cid][$key] = $value;
    }

    /**
     * Получить значение из контекста корутины
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cid = self::getId();

        return self::$contexts[$cid][$key] ?? $default;
    }

    /**
     * Проверить существование ключа в контексте
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        $cid = self::getId();

        return isset(self::$contexts[$cid][$key]);
    }

    /**
     * Удалить значение из контекста
     *
     * @param string $key
     */
    public static function forget(string $key): void
    {
        $cid = self::getId();

        unset(self::$contexts[$cid][$key]);
    }

    /**
     * Получить весь контекст корутины
     *
     * @return array
     */
    public static function getContext(): array
    {
        $cid = self::getId();

        return self::$contexts[$cid] ?? [];
    }

    /**
     * Очистить контекст текущей корутины
     */
    public static function clear(): void
    {
        $cid = self::getId();

        unset(self::$contexts[$cid]);
    }

    /**
     * Очистить все контексты (для тестов)
     */
    public static function clearAll(): void
    {
        self::$contexts = [];
    }

    /**
     * Получить количество активных контекстов
     *
     * @return int
     */
    public static function count(): int
    {
        return count(self::$contexts);
    }

    /**
     * Выполнить callback с автоматической очисткой контекста
     *
     * @param callable $callback
     * @return mixed
     */
    public static function withContext(callable $callback): mixed
    {
        try {
            return $callback();
        } finally {
            self::clear();
        }
    }
}
