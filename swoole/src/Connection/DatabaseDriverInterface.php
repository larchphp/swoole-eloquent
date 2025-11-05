<?php

declare(strict_types=1);

namespace SwooleEloquent\Connection;

/**
 * Интерфейс для универсальной поддержки различных БД
 */
interface DatabaseDriverInterface
{
    /**
     * Выполнить SELECT запрос
     *
     * @param string $query SQL запрос
     * @param array $bindings Параметры привязки
     * @return array Массив результатов
     */
    public function select(string $query, array $bindings = []): array;

    /**
     * Выполнить INSERT запрос
     *
     * @param string $query SQL запрос
     * @param array $bindings Параметры привязки
     * @return bool Успешность выполнения
     */
    public function insert(string $query, array $bindings = []): bool;

    /**
     * Выполнить UPDATE запрос
     *
     * @param string $query SQL запрос
     * @param array $bindings Параметры привязки
     * @return int Количество затронутых строк
     */
    public function update(string $query, array $bindings = []): int;

    /**
     * Выполнить DELETE запрос
     *
     * @param string $query SQL запрос
     * @param array $bindings Параметры привязки
     * @return int Количество удаленных строк
     */
    public function delete(string $query, array $bindings = []): int;

    /**
     * Выполнить произвольный statement
     *
     * @param string $query SQL запрос
     * @param array $bindings Параметры привязки
     * @return bool Успешность выполнения
     */
    public function statement(string $query, array $bindings = []): bool;

    /**
     * Начать транзакцию
     *
     * @return bool Успешность начала транзакции
     */
    public function beginTransaction(): bool;

    /**
     * Зафиксировать транзакцию
     *
     * @return bool Успешность коммита
     */
    public function commit(): bool;

    /**
     * Откатить транзакцию
     *
     * @return bool Успешность отката
     */
    public function rollBack(): bool;

    /**
     * Проверить активность соединения
     *
     * @return bool Активно ли соединение
     */
    public function isAlive(): bool;

    /**
     * Переподключиться к БД
     *
     * @return bool Успешность переподключения
     */
    public function reconnect(): bool;

    /**
     * Получить ID последней вставленной записи
     *
     * @param string|null $sequence Имя последовательности (для PostgreSQL)
     * @return string|int ID последней записи
     */
    public function lastInsertId(?string $sequence = null): string|int;
}
