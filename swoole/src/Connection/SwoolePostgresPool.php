<?php

declare(strict_types=1);

namespace SwooleEloquent\Connection;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\PostgreSQL;
use RuntimeException;

/**
 * Пул соединений PostgreSQL на основе Swoole корутин
 */
class SwoolePostgresPool
{
    private Channel $pool;
    private array $config;
    private int $size;
    private int $created = 0;
    private int $activeConnections = 0;
    private int $waitingRequests = 0;

    /**
     * @param array $config Конфигурация подключения [host, port, dbname, user, password]
     * @param int $size Размер пула
     */
    public function __construct(array $config, int $size = 10)
    {
        if (!extension_loaded('swoole')) {
            throw new RuntimeException('Swoole extension is required');
        }

        if (!extension_loaded('pgsql')) {
            throw new RuntimeException('PostgreSQL extension is required');
        }

        $this->config = $config;
        $this->size = $size;
        $this->pool = new Channel($size);

        $this->initializePool();
    }

    /**
     * Инициализировать пул соединений
     */
    private function initializePool(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $connection = $this->createConnection();
            if ($connection) {
                $this->pool->push($connection);
                $this->created++;
            }
        }
    }

    /**
     * Создать новое соединение
     */
    private function createConnection(): ?PostgreSQL
    {
        $pg = new PostgreSQL();
        $connectionString = sprintf(
            'host=%s port=%d dbname=%s user=%s password=%s',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 5432,
            $this->config['database'] ?? '',
            $this->config['username'] ?? '',
            $this->config['password'] ?? ''
        );

        $connected = $pg->connect($connectionString);

        if (!$connected) {
            return null;
        }

        return $pg;
    }

    /**
     * Получить соединение из пула
     *
     * @param float $timeout Таймаут ожидания в секундах
     * @return PostgreSQL|null
     */
    public function get(float $timeout = 5.0): ?PostgreSQL
    {
        $this->waitingRequests++;

        $connection = $this->pool->pop($timeout);

        $this->waitingRequests--;

        if ($connection === false) {
            throw new RuntimeException('Connection pool timeout');
        }

        // Проверка активности соединения
        if (!$this->isConnectionAlive($connection)) {
            $connection = $this->createConnection();
            if (!$connection) {
                throw new RuntimeException('Failed to create new connection');
            }
        }

        $this->activeConnections++;

        return $connection;
    }

    /**
     * Вернуть соединение в пул
     *
     * @param PostgreSQL $connection
     */
    public function put(PostgreSQL $connection): void
    {
        $this->activeConnections--;

        // Проверяем, что соединение не в транзакции
        if ($this->pool->push($connection, 0.1) === false) {
            // Пул переполнен, закрываем соединение
            unset($connection);
        }
    }

    /**
     * Проверить, активно ли соединение
     *
     * @param PostgreSQL $connection
     * @return bool
     */
    private function isConnectionAlive(PostgreSQL $connection): bool
    {
        try {
            $result = $connection->query('SELECT 1');
            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Получить метрики пула
     *
     * @return array
     */
    public function getMetrics(): array
    {
        return [
            'size' => $this->size,
            'created' => $this->created,
            'active' => $this->activeConnections,
            'idle' => $this->pool->length(),
            'waiting' => $this->waitingRequests,
        ];
    }

    /**
     * Закрыть все соединения в пуле
     */
    public function close(): void
    {
        while (!$this->pool->isEmpty()) {
            $connection = $this->pool->pop(0.1);
            if ($connection) {
                unset($connection);
            }
        }

        $this->pool->close();
        $this->created = 0;
        $this->activeConnections = 0;
    }

    /**
     * Деструктор
     */
    public function __destruct()
    {
        $this->close();
    }
}
