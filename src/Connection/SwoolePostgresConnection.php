<?php

declare(strict_types=1);

namespace SwooleEloquent\Connection;

use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as SchemaGrammar;
use Illuminate\Database\Schema\PostgresBuilder;
use Swoole\Coroutine\PostgreSQL;
use RuntimeException;

/**
 * PostgreSQL соединение через Swoole с пулом соединений
 */
class SwoolePostgresConnection extends AbstractSwooleConnection
{
    private SwoolePostgresPool $pool;
    private ?PostgreSQL $currentConnection = null;

    /**
     * @param array $config Конфигурация подключения
     */
    public function __construct(array $config)
    {
        $poolSize = $config['pool_size'] ?? 10;
        $this->pool = new SwoolePostgresPool($config, $poolSize);

        $driver = new SwoolePostgresDriver($this->pool);

        parent::__construct(
            $driver,
            $config['database'] ?? '',
            $config['prefix'] ?? '',
            $config
        );

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
    }

    /**
     * Получить грамматику запросов по умолчанию
     *
     * @return PostgresGrammar
     */
    protected function getDefaultQueryGrammar(): PostgresGrammar
    {
        return new PostgresGrammar();
    }

    /**
     * Получить процессор запросов по умолчанию
     *
     * @return PostgresProcessor
     */
    protected function getDefaultPostProcessor(): PostgresProcessor
    {
        return new PostgresProcessor();
    }

    /**
     * Получить грамматику схемы по умолчанию
     *
     * @return SchemaGrammar
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return new SchemaGrammar();
    }

    /**
     * Получить построитель схемы
     *
     * @return PostgresBuilder
     */
    public function getSchemaBuilder(): PostgresBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new PostgresBuilder($this);
    }

    /**
     * Получить метрики пула соединений
     *
     * @return array
     */
    public function getPoolMetrics(): array
    {
        return $this->pool->getMetrics();
    }

    /**
     * Закрыть пул соединений
     */
    public function disconnect(): void
    {
        $this->pool->close();
    }
}

/**
 * Драйвер PostgreSQL для Swoole
 */
class SwoolePostgresDriver implements DatabaseDriverInterface
{
    private SwoolePostgresPool $pool;
    private ?PostgreSQL $transactionConnection = null;
    private string $lastInsertId = '';

    public function __construct(SwoolePostgresPool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * Получить соединение (из пула или текущее в транзакции)
     */
    private function getConnection(): PostgreSQL
    {
        if ($this->transactionConnection !== null) {
            return $this->transactionConnection;
        }

        return $this->pool->get();
    }

    /**
     * Вернуть соединение в пул (если не в транзакции)
     */
    private function releaseConnection(PostgreSQL $connection): void
    {
        if ($this->transactionConnection === null) {
            $this->pool->put($connection);
        }
    }

    public function select(string $query, array $bindings = []): array
    {
        $connection = $this->getConnection();

        try {
            $result = $connection->query($this->bindValues($query, $bindings));

            if ($result === false) {
                throw new RuntimeException($connection->error);
            }

            $rows = [];
            while ($row = $connection->fetchAssoc($result)) {
                $rows[] = (object) $row;
            }

            return $rows;
        } finally {
            $this->releaseConnection($connection);
        }
    }

    public function insert(string $query, array $bindings = []): bool
    {
        $connection = $this->getConnection();

        try {
            $result = $connection->query($this->bindValues($query, $bindings));

            if ($result === false) {
                throw new RuntimeException($connection->error);
            }

            // Сохраняем ID последней вставленной записи для sequences
            if (stripos($query, 'returning') !== false) {
                $row = $connection->fetchRow($result);
                if ($row && isset($row[0])) {
                    $this->lastInsertId = (string) $row[0];
                }
            }

            return true;
        } finally {
            $this->releaseConnection($connection);
        }
    }

    public function update(string $query, array $bindings = []): int
    {
        $connection = $this->getConnection();

        try {
            $result = $connection->query($this->bindValues($query, $bindings));

            if ($result === false) {
                throw new RuntimeException($connection->error);
            }

            return $connection->affectedRows($result);
        } finally {
            $this->releaseConnection($connection);
        }
    }

    public function delete(string $query, array $bindings = []): int
    {
        return $this->update($query, $bindings);
    }

    public function statement(string $query, array $bindings = []): bool
    {
        $connection = $this->getConnection();

        try {
            $result = $connection->query($this->bindValues($query, $bindings));

            if ($result === false) {
                throw new RuntimeException($connection->error);
            }

            return true;
        } finally {
            $this->releaseConnection($connection);
        }
    }

    public function beginTransaction(): bool
    {
        if ($this->transactionConnection === null) {
            $this->transactionConnection = $this->pool->get();
            return $this->transactionConnection->query('BEGIN') !== false;
        }

        return true;
    }

    public function commit(): bool
    {
        if ($this->transactionConnection !== null) {
            $result = $this->transactionConnection->query('COMMIT');
            $this->pool->put($this->transactionConnection);
            $this->transactionConnection = null;
            return $result !== false;
        }

        return false;
    }

    public function rollBack(): bool
    {
        if ($this->transactionConnection !== null) {
            $result = $this->transactionConnection->query('ROLLBACK');
            $this->pool->put($this->transactionConnection);
            $this->transactionConnection = null;
            return $result !== false;
        }

        return false;
    }

    public function isAlive(): bool
    {
        try {
            $connection = $this->pool->get(1.0);
            $result = $connection->query('SELECT 1');
            $this->pool->put($connection);
            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function reconnect(): bool
    {
        // Пул автоматически управляет переподключениями
        return true;
    }

    public function lastInsertId(?string $sequence = null): string|int
    {
        return $this->lastInsertId;
    }

    /**
     * Привязать значения к запросу (простая реализация)
     */
    private function bindValues(string $query, array $bindings): string
    {
        if (empty($bindings)) {
            return $query;
        }

        // Простая замена ? на значения (в продакшене нужно использовать prepared statements)
        foreach ($bindings as $value) {
            $escaped = $this->escapeValue($value);
            $query = preg_replace('/\?/', $escaped, $query, 1);
        }

        return $query;
    }

    /**
     * Экранировать значение для PostgreSQL
     */
    private function escapeValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Для строк экранируем одинарные кавычки
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
