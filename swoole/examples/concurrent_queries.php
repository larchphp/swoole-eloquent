<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swoole\Coroutine;
use SwooleEloquent\Connection\SwoolePostgresConnection;
use SwooleEloquent\ORM\AsyncModel;
use SwooleEloquent\Support\Helpers;

/**
 * Модель пользователя
 */
class User extends AsyncModel
{
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'age'];
}

/**
 * Модель поста
 */
class Post extends AsyncModel
{
    protected ?string $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'content'];
}

// Запуск в корутине
Coroutine\run(function () {
    // Настройка соединения
    $connection = new SwoolePostgresConnection([
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'test_db',
        'username' => 'postgres',
        'password' => 'password',
        'pool_size' => 50, // Большой пул для параллельных запросов
    ]);

    User::setConnectionResolver($connection);
    Post::setConnectionResolver($connection);

    echo "=== Swoole-Eloquent Concurrent Queries Example ===" . PHP_EOL . PHP_EOL;

    // 1. Параллельные запросы - простой пример
    echo "1. Parallel queries (10 concurrent):" . PHP_EOL;

    $startTime = microtime(true);

    $channels = [];
    for ($i = 0; $i < 10; $i++) {
        $channels[$i] = new Coroutine\Channel(1);

        Coroutine::create(function () use ($i, $channels) {
            $user = User::async()->find($i + 1);
            $channels[$i]->push($user);
        });
    }

    // Собираем результаты
    $results = [];
    for ($i = 0; $i < 10; $i++) {
        $results[] = $channels[$i]->pop();
    }

    $duration = (microtime(true) - $startTime) * 1000;
    echo "   Completed 10 queries in " . number_format($duration, 2) . " ms" . PHP_EOL;
    echo "   Average: " . number_format($duration / 10, 2) . " ms per query" . PHP_EOL;
    echo PHP_EOL;

    // 2. Массовые параллельные запросы (100 запросов)
    echo "2. Massive parallel queries (100 concurrent):" . PHP_EOL;

    $startTime = microtime(true);

    $wg = new Coroutine\WaitGroup();

    $successCount = 0;

    for ($i = 0; $i < 100; $i++) {
        $wg->add();

        Coroutine::create(function () use ($i, $wg, &$successCount) {
            try {
                $user = User::async()->where('id', '>=', $i)->first();
                if ($user) {
                    $successCount++;
                }
            } finally {
                $wg->done();
            }
        });
    }

    $wg->wait();

    $duration = (microtime(true) - $startTime) * 1000;
    echo "   Completed 100 queries in " . number_format($duration, 2) . " ms" . PHP_EOL;
    echo "   Average: " . number_format($duration / 100, 2) . " ms per query" . PHP_EOL;
    echo "   Success: {$successCount}/100" . PHP_EOL;
    echo PHP_EOL;

    // 3. Комплексные параллельные операции
    echo "3. Complex parallel operations:" . PHP_EOL;

    $startTime = microtime(true);

    $wg = new Coroutine\WaitGroup();

    // Одновременно:
    // - Создать 5 пользователей
    // - Обновить 5 пользователей
    // - Удалить 5 пользователей
    // - Выполнить 10 выборок

    $operations = [
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'selected' => 0,
    ];

    // Создание
    for ($i = 0; $i < 5; $i++) {
        $wg->add();
        Coroutine::create(function () use ($i, $wg, &$operations) {
            $user = new User();
            $user->name = "Concurrent User {$i}";
            $user->email = "concurrent{$i}@example.com";
            $user->age = 20 + $i;

            if ($user->asyncSave()) {
                $operations['created']++;
            }

            $wg->done();
        });
    }

    // Обновление
    for ($i = 1; $i <= 5; $i++) {
        $wg->add();
        Coroutine::create(function () use ($i, $wg, &$operations) {
            $affected = User::async()
                ->where('id', $i)
                ->update(['age' => 25 + $i]);

            if ($affected > 0) {
                $operations['updated']++;
            }

            $wg->done();
        });
    }

    // Выборки
    for ($i = 0; $i < 10; $i++) {
        $wg->add();
        Coroutine::create(function () use ($i, $wg, &$operations) {
            $users = User::async()
                ->where('age', '>', 20)
                ->limit(10)
                ->get();

            if (!empty($users)) {
                $operations['selected']++;
            }

            $wg->done();
        });
    }

    $wg->wait();

    $duration = (microtime(true) - $startTime) * 1000;
    echo "   Completed in " . number_format($duration, 2) . " ms" . PHP_EOL;
    echo "   Created: {$operations['created']}/5" . PHP_EOL;
    echo "   Updated: {$operations['updated']}/5" . PHP_EOL;
    echo "   Selected: {$operations['selected']}/10" . PHP_EOL;
    echo PHP_EOL;

    // 4. Метрики пула соединений
    echo "4. Connection pool metrics:" . PHP_EOL;
    $metrics = $connection->getPoolMetrics();
    echo "   Pool size: {$metrics['size']}" . PHP_EOL;
    echo "   Active connections: {$metrics['active']}" . PHP_EOL;
    echo "   Idle connections: {$metrics['idle']}" . PHP_EOL;
    echo "   Waiting requests: {$metrics['waiting']}" . PHP_EOL;
    echo PHP_EOL;

    // 5. Стресс-тест (1000 запросов)
    echo "5. Stress test (1000 concurrent queries):" . PHP_EOL;
    echo "   Starting..." . PHP_EOL;

    $startTime = microtime(true);
    $wg = new Coroutine\WaitGroup();
    $completed = 0;
    $failed = 0;

    for ($i = 0; $i < 1000; $i++) {
        $wg->add();

        Coroutine::create(function () use ($i, $wg, &$completed, &$failed) {
            try {
                $count = User::async()->count();
                if ($count >= 0) {
                    $completed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            } finally {
                $wg->done();
            }
        });
    }

    $wg->wait();

    $duration = (microtime(true) - $startTime) * 1000;
    $qps = 1000 / ($duration / 1000);

    echo "   Completed: {$completed}/1000" . PHP_EOL;
    echo "   Failed: {$failed}/1000" . PHP_EOL;
    echo "   Total time: " . number_format($duration, 2) . " ms" . PHP_EOL;
    echo "   QPS: " . number_format($qps, 2) . " queries/sec" . PHP_EOL;
    echo PHP_EOL;

    // Закрытие соединения
    $connection->disconnect();

    echo "=== Example completed ===" . PHP_EOL;
});
