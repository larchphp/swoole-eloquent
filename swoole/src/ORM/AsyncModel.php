<?php

declare(strict_types=1);

namespace SwooleEloquent\ORM;

use SwooleEloquent\Connection\AbstractSwooleConnection;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Базовая асинхронная модель
 * Использование: User::async()->find(1)
 */
abstract class AsyncModel
{
    /**
     * Имя таблицы
     *
     * @var string|null
     */
    protected ?string $table = null;

    /**
     * Первичный ключ
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * Атрибуты модели
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * Оригинальные атрибуты (для отслеживания изменений)
     *
     * @var array
     */
    protected array $original = [];

    /**
     * Массово заполняемые атрибуты
     *
     * @var array
     */
    protected array $fillable = [];

    /**
     * Защищенные атрибуты (не массово заполняемые)
     *
     * @var array
     */
    protected array $guarded = ['*'];

    /**
     * Атрибуты, которые должны быть скрыты при сериализации
     *
     * @var array
     */
    protected array $hidden = [];

    /**
     * Атрибуты, которые должны быть видимы при сериализации
     *
     * @var array
     */
    protected array $visible = [];

    /**
     * Временные метки (created_at, updated_at)
     *
     * @var bool
     */
    public bool $timestamps = true;

    /**
     * Существует ли модель в БД
     *
     * @var bool
     */
    protected bool $exists = false;

    /**
     * Статическое соединение с БД
     *
     * @var ConnectionInterface|null
     */
    protected static ?ConnectionInterface $resolver = null;

    /**
     * Создать новый async query builder для модели
     *
     * @return AsyncQueryBuilder
     */
    public static function async(): AsyncQueryBuilder
    {
        $instance = new static();
        $connection = static::getConnectionResolver();

        $builder = new AsyncQueryBuilder($connection, static::class);
        $builder->table($instance->getTable());

        return $builder;
    }

    /**
     * Получить имя таблицы
     *
     * @return string
     */
    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        // Автоматически определяем имя таблицы из имени класса
        $className = class_basename(static::class);
        return strtolower($className) . 's';
    }

    /**
     * Получить имя первичного ключа
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Получить значение первичного ключа
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Установить соединение
     *
     * @param ConnectionInterface $connection
     */
    public static function setConnectionResolver(ConnectionInterface $connection): void
    {
        static::$resolver = $connection;
    }

    /**
     * Получить соединение
     *
     * @return ConnectionInterface
     */
    public static function getConnectionResolver(): ConnectionInterface
    {
        if (static::$resolver === null) {
            throw new RuntimeException('Connection resolver not set. Call AsyncModel::setConnectionResolver() first.');
        }

        return static::$resolver;
    }

    /**
     * Асинхронно сохранить модель
     *
     * @return bool
     */
    public function asyncSave(): bool
    {
        $connection = static::getConnectionResolver();
        $builder = $connection->table($this->getTable());

        if ($this->exists) {
            // UPDATE
            $dirty = $this->getDirty();

            if (empty($dirty)) {
                return true;
            }

            if ($this->timestamps) {
                $dirty['updated_at'] = date('Y-m-d H:i:s');
            }

            $affected = $builder
                ->where($this->getKeyName(), $this->getKey())
                ->update($dirty);

            if ($affected > 0) {
                $this->syncOriginal();
                return true;
            }

            return false;
        } else {
            // INSERT
            $attributes = $this->attributes;

            if ($this->timestamps) {
                $now = date('Y-m-d H:i:s');
                $attributes['created_at'] = $now;
                $attributes['updated_at'] = $now;
            }

            $id = $builder->insertGetId($attributes, $this->getKeyName());

            if ($id) {
                $this->setAttribute($this->getKeyName(), $id);
                $this->exists = true;
                $this->syncOriginal();
                return true;
            }

            return false;
        }
    }

    /**
     * Асинхронно обновить модель
     *
     * @param array $attributes
     * @return bool
     */
    public function asyncUpdate(array $attributes = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->fill($attributes);

        return $this->asyncSave();
    }

    /**
     * Асинхронно удалить модель
     *
     * @return bool
     */
    public function asyncDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $connection = static::getConnectionResolver();
        $deleted = $connection
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Асинхронно перезагрузить модель из БД
     *
     * @return bool
     */
    public function asyncRefresh(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $fresh = static::async()->find($this->getKey());

        if ($fresh === null) {
            return false;
        }

        $this->attributes = $fresh->attributes;
        $this->syncOriginal();

        return true;
    }

    /**
     * Массово заполнить атрибуты
     *
     * @param array $attributes
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Проверить, является ли атрибут массово заполняемым
     *
     * @param string $key
     * @return bool
     */
    protected function isFillable(string $key): bool
    {
        // Если fillable определен, используем его
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        // Если guarded = ['*'], все защищено
        if (in_array('*', $this->guarded)) {
            return false;
        }

        // Если в guarded, то не fillable
        return !in_array($key, $this->guarded);
    }

    /**
     * Получить атрибут
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Установить атрибут
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Получить измененные атрибуты
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Синхронизировать оригинальные атрибуты с текущими
     *
     * @return self
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * Магический геттер
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Магический сеттер
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Магический isset
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Магический unset
     *
     * @param string $key
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Преобразовать модель в массив
     *
     * @return array
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        // Скрыть атрибуты
        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        // Показать только видимые
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        return $attributes;
    }

    /**
     * Преобразовать модель в JSON
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
