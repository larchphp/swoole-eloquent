<?php

declare(strict_types=1);

namespace SwooleEloquent\Connection;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Closure;

/**
 * Базовый класс для асинхронных соединений Swoole
 */
abstract class AbstractSwooleConnection extends Connection
{
    protected DatabaseDriverInterface $driver;
    protected int $transactionLevel = 0;

    /**
     * @param DatabaseDriverInterface $driver
     * @param string $database
     * @param string $tablePrefix
     * @param array $config
     */
    public function __construct(
        DatabaseDriverInterface $driver,
        string $database = '',
        string $tablePrefix = '',
        array $config = []
    ) {
        $this->driver = $driver;

        // Вызываем конструктор родительского класса
        // В качестве PDO передаем null, так как мы используем Swoole драйвер
        parent::__construct(null, $database, $tablePrefix, $config);
    }

    /**
     * Выполнить SELECT запрос
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            // Логирование запроса
            $this->logQuery($query, $bindings);

            return $this->driver->select($query, $this->prepareBindings($bindings));
        });
    }

    /**
     * Выполнить INSERT запрос
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Выполнить UPDATE запрос
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function update($query, $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Выполнить DELETE запрос
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function delete($query, $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Выполнить SQL statement
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function statement($query, $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $this->logQuery($query, $bindings);

            return $this->driver->statement($query, $this->prepareBindings($bindings));
        });
    }

    /**
     * Выполнить statement с возвратом количества затронутых строк
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $this->logQuery($query, $bindings);

            // Для UPDATE/DELETE используем соответствующие методы драйвера
            if (stripos(trim($query), 'update') === 0) {
                return $this->driver->update($query, $this->prepareBindings($bindings));
            }

            if (stripos(trim($query), 'delete') === 0) {
                return $this->driver->delete($query, $this->prepareBindings($bindings));
            }

            // Для других запросов используем statement
            $this->driver->statement($query, $this->prepareBindings($bindings));
            return 0;
        });
    }

    /**
     * Начать транзакцию
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->driver->beginTransaction();
        }

        $this->transactionLevel++;

        $this->fireConnectionEvent('beganTransaction');
    }

    /**
     * Зафиксировать транзакцию
     *
     * @return void
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 1) {
            $this->driver->commit();
        }

        $this->transactionLevel = max(0, $this->transactionLevel - 1);

        $this->fireConnectionEvent('committed');
    }

    /**
     * Откатить транзакцию
     *
     * @param int|null $toLevel
     * @return void
     */
    public function rollBack($toLevel = null): void
    {
        $toLevel = is_null($toLevel) ? $this->transactionLevel - 1 : $toLevel;

        if ($toLevel < 0 || $toLevel >= $this->transactionLevel) {
            return;
        }

        if ($toLevel === 0) {
            $this->driver->rollBack();
        }

        $this->transactionLevel = $toLevel;

        $this->fireConnectionEvent('rollingBack');
    }

    /**
     * Получить уровень транзакций
     *
     * @return int
     */
    public function transactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Выполнить запрос с обработкой ошибок
     *
     * @param string $query
     * @param array $bindings
     * @param Closure $callback
     * @return mixed
     */
    protected function run($query, $bindings, Closure $callback): mixed
    {
        try {
            return $callback($query, $bindings);
        } catch (\Throwable $e) {
            // Здесь можно добавить логику повторных попыток
            throw $e;
        }
    }

    /**
     * Получить драйвер
     *
     * @return DatabaseDriverInterface
     */
    public function getDriver(): DatabaseDriverInterface
    {
        return $this->driver;
    }

    /**
     * Получить PDO для записи (не используется в Swoole)
     *
     * @return null
     */
    public function getPdo(): null
    {
        return null;
    }

    /**
     * Получить PDO для чтения (не используется в Swoole)
     *
     * @return null
     */
    public function getReadPdo(): null
    {
        return null;
    }
}
