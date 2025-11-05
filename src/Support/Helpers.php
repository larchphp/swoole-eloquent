<?php

declare(strict_types=1);

namespace SwooleEloquent\Support;

/**
 * Вспомогательные утилиты
 */
class Helpers
{
    /**
     * Конвертировать PostgreSQL значение в PHP тип
     *
     * @param mixed $value
     * @param string|null $type
     * @return mixed
     */
    public static function pgToPHP(mixed $value, ?string $type = null): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool', 'boolean' => $value === 't' || $value === 'true' || $value === true,
            'int', 'integer', 'int4', 'int8' => (int) $value,
            'float', 'float4', 'float8', 'numeric', 'decimal' => (float) $value,
            'json', 'jsonb' => json_decode($value, true),
            'array' => self::parsePostgresArray($value),
            'timestamp', 'timestamptz', 'date', 'time' => $value,
            default => $value,
        };
    }

    /**
     * Конвертировать PHP значение в PostgreSQL формат
     *
     * @param mixed $value
     * @return mixed
     */
    public static function phpToPG(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 't' : 'f';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /**
     * Парсинг PostgreSQL массива
     *
     * @param string $value
     * @return array
     */
    public static function parsePostgresArray(string $value): array
    {
        if (empty($value) || $value === '{}') {
            return [];
        }

        // Убираем фигурные скобки
        $value = trim($value, '{}');

        // Разбиваем по запятым (упрощенная версия)
        $items = explode(',', $value);

        return array_map(function ($item) {
            $item = trim($item);
            // Убираем кавычки если есть
            if (str_starts_with($item, '"') && str_ends_with($item, '"')) {
                $item = substr($item, 1, -1);
            }
            return $item;
        }, $items);
    }

    /**
     * Логирование с временной меткой
     *
     * @param string $message
     * @param array $context
     * @param string $level
     */
    public static function log(string $message, array $context = [], string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';

        echo "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
    }

    /**
     * Измерить время выполнения callback
     *
     * @param callable $callback
     * @param string|null $label
     * @return mixed
     */
    public static function benchmark(callable $callback, ?string $label = null): mixed
    {
        $start = microtime(true);

        $result = $callback();

        $duration = (microtime(true) - $start) * 1000; // в миллисекундах

        $labelStr = $label ? " ({$label})" : '';
        self::log("Execution time{$labelStr}: " . number_format($duration, 2) . ' ms', [], 'debug');

        return $result;
    }

    /**
     * Безопасное получение значения из массива
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function arrayGet(array $array, string $key, mixed $default = null): mixed
    {
        // Поддержка dot нотации: 'user.profile.name'
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $array;

            foreach ($keys as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $default;
                }
                $value = $value[$segment];
            }

            return $value;
        }

        return $array[$key] ?? $default;
    }

    /**
     * Преобразовать snake_case в camelCase
     *
     * @param string $value
     * @return string
     */
    public static function camelCase(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }

    /**
     * Преобразовать camelCase в snake_case
     *
     * @param string $value
     * @return string
     */
    public static function snakeCase(string $value): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
    }

    /**
     * Получить базовое имя класса (без namespace)
     *
     * @param string|object $class
     * @return string
     */
    public static function classBasename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Форматировать размер в байтах
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Валидация email
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Генерация UUID v4
     *
     * @return string
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Retry механизм для выполнения операции
     *
     * @param callable $callback
     * @param int $times Количество попыток
     * @param int $sleep Задержка между попытками в миллисекундах
     * @return mixed
     * @throws \Exception
     */
    public static function retry(callable $callback, int $times = 3, int $sleep = 100): mixed
    {
        $attempts = 0;

        beginning:
        $attempts++;

        try {
            return $callback($attempts);
        } catch (\Exception $e) {
            if ($attempts >= $times) {
                throw $e;
            }

            if ($sleep > 0) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }
}
