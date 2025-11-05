<?php

declare(strict_types=1);

namespace SwooleEloquent\ORM;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\ConnectionInterface;
use Closure;

/**
 * Асинхронный Query Builder для построения и выполнения запросов
 * Синтаксис: Model::async()->where('id', 1)->get()
 */
class AsyncQueryBuilder
{
    protected Builder $builder;
    protected ConnectionInterface $connection;
    protected ?string $model = null;

    /**
     * @param ConnectionInterface $connection
     * @param string|null $model Класс модели для гидратации результатов
     */
    public function __construct(ConnectionInterface $connection, ?string $model = null)
    {
        $this->connection = $connection;
        $this->builder = $connection->query();
        $this->model = $model;
    }

    /**
     * Установить таблицу для запроса
     *
     * @param string $table
     * @return self
     */
    public function table(string $table): self
    {
        $this->builder->from($table);
        return $this;
    }

    /**
     * Найти запись по ID
     *
     * @param int|string $id
     * @param array $columns
     * @return object|null
     */
    public function find(int|string $id, array $columns = ['*']): ?object
    {
        $result = $this->builder
            ->where('id', '=', $id)
            ->first($columns);

        if ($result === null) {
            return null;
        }

        return $this->hydrateModel($result);
    }

    /**
     * Найти запись по ID или выбросить исключение
     *
     * @param int|string $id
     * @param array $columns
     * @return object
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int|string $id, array $columns = ['*']): object
    {
        $result = $this->find($id, $columns);

        if ($result === null) {
            throw new \RuntimeException("Model not found with ID: {$id}");
        }

        return $result;
    }

    /**
     * Добавить условие WHERE
     *
     * @param string|Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return self
     */
    public function where(string|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        $this->builder->where($column, $operator, $value, $boolean);
        return $this;
    }

    /**
     * Добавить условие OR WHERE
     *
     * @param string|Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @return self
     */
    public function orWhere(string|Closure $column, mixed $operator = null, mixed $value = null): self
    {
        $this->builder->orWhere($column, $operator, $value);
        return $this;
    }

    /**
     * Добавить условие WHERE IN
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @param bool $not
     * @return self
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $this->builder->whereIn($column, $values, $boolean, $not);
        return $this;
    }

    /**
     * Добавить условие WHERE NOT IN
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return self
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        $this->builder->whereNotIn($column, $values, $boolean);
        return $this;
    }

    /**
     * Добавить условие WHERE NULL
     *
     * @param string $column
     * @param string $boolean
     * @param bool $not
     * @return self
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $this->builder->whereNull($column, $boolean, $not);
        return $this;
    }

    /**
     * Добавить условие WHERE NOT NULL
     *
     * @param string $column
     * @param string $boolean
     * @return self
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        $this->builder->whereNotNull($column, $boolean);
        return $this;
    }

    /**
     * Получить все результаты
     *
     * @param array $columns
     * @return array
     */
    public function get(array $columns = ['*']): array
    {
        $results = $this->builder->get($columns);

        if ($this->model === null) {
            return $results->all();
        }

        // Гидратация моделей
        return array_map(
            fn($result) => $this->hydrateModel($result),
            $results->all()
        );
    }

    /**
     * Получить первый результат
     *
     * @param array $columns
     * @return object|null
     */
    public function first(array $columns = ['*']): ?object
    {
        $result = $this->builder->first($columns);

        if ($result === null) {
            return null;
        }

        return $this->hydrateModel($result);
    }

    /**
     * Получить количество записей
     *
     * @param string $columns
     * @return int
     */
    public function count(string $columns = '*'): int
    {
        return $this->builder->count($columns);
    }

    /**
     * Проверить существование записей
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->builder->exists();
    }

    /**
     * Вставить запись
     *
     * @param array $values
     * @return bool
     */
    public function insert(array $values): bool
    {
        return $this->builder->insert($values);
    }

    /**
     * Вставить запись и получить ID
     *
     * @param array $values
     * @param string|null $sequence
     * @return int
     */
    public function insertGetId(array $values, ?string $sequence = null): int
    {
        return $this->builder->insertGetId($values, $sequence);
    }

    /**
     * Обновить записи
     *
     * @param array $values
     * @return int Количество обновленных строк
     */
    public function update(array $values): int
    {
        return $this->builder->update($values);
    }

    /**
     * Удалить записи
     *
     * @param int|string|null $id
     * @return int Количество удаленных строк
     */
    public function delete(int|string|null $id = null): int
    {
        if ($id !== null) {
            $this->where('id', '=', $id);
        }

        return $this->builder->delete();
    }

    /**
     * Добавить ORDER BY
     *
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->builder->orderBy($column, $direction);
        return $this;
    }

    /**
     * Добавить LIMIT
     *
     * @param int $value
     * @return self
     */
    public function limit(int $value): self
    {
        $this->builder->limit($value);
        return $this;
    }

    /**
     * Добавить OFFSET
     *
     * @param int $value
     * @return self
     */
    public function offset(int $value): self
    {
        $this->builder->offset($value);
        return $this;
    }

    /**
     * Добавить GROUP BY
     *
     * @param string ...$groups
     * @return self
     */
    public function groupBy(string ...$groups): self
    {
        $this->builder->groupBy(...$groups);
        return $this;
    }

    /**
     * Добавить HAVING
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return self
     */
    public function having(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        $this->builder->having($column, $operator, $value, $boolean);
        return $this;
    }

    /**
     * Добавить JOIN
     *
     * @param string $table
     * @param string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @return self
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'inner'): self
    {
        $this->builder->join($table, $first, $operator, $second, $type);
        return $this;
    }

    /**
     * Добавить LEFT JOIN
     *
     * @param string $table
     * @param string $first
     * @param string|null $operator
     * @param string|null $second
     * @return self
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        $this->builder->leftJoin($table, $first, $operator, $second);
        return $this;
    }

    /**
     * Выбрать столбцы
     *
     * @param array|string $columns
     * @return self
     */
    public function select(array|string $columns = ['*']): self
    {
        $this->builder->select($columns);
        return $this;
    }

    /**
     * Гидратировать модель из результата
     *
     * @param object $result
     * @return object
     */
    protected function hydrateModel(object $result): object
    {
        if ($this->model === null) {
            return $result;
        }

        // Если указан класс модели, создаем экземпляр
        if (class_exists($this->model)) {
            $instance = new $this->model();

            // Заполняем атрибуты
            foreach ((array) $result as $key => $value) {
                $instance->{$key} = $value;
            }

            // Помечаем как существующую (не новую)
            if (method_exists($instance, 'syncOriginal')) {
                $instance->syncOriginal();
            }

            return $instance;
        }

        return $result;
    }

    /**
     * Получить базовый Query Builder
     *
     * @return Builder
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }
}
