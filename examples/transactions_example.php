<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swoole\Coroutine;
use SwooleEloquent\Connection\SwoolePostgresConnection;
use SwooleEloquent\ORM\AsyncModel;

/**
 * Модель пользователя
 */
class User extends AsyncModel
{
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'balance'];
}

/**
 * Модель транзакции
 */
class Transaction extends AsyncModel
{
    protected ?string $table = 'transactions';
    protected array $fillable = ['from_user_id', 'to_user_id', 'amount', 'status'];
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
        'pool_size' => 10,
    ]);

    User::setConnectionResolver($connection);
    Transaction::setConnectionResolver($connection);

    echo "=== Swoole-Eloquent Transactions Example ===" . PHP_EOL . PHP_EOL;

    // 1. Простая транзакция
    echo "1. Simple transaction (create user):" . PHP_EOL;

    try {
        $connection->beginTransaction();

        $user = new User();
        $user->name = 'Transaction User';
        $user->email = 'tx@example.com';
        $user->balance = 1000;
        $user->asyncSave();

        echo "   User created with ID: {$user->id}, balance: {$user->balance}" . PHP_EOL;

        $connection->commit();
        echo "   Transaction committed" . PHP_EOL;
    } catch (\Throwable $e) {
        $connection->rollBack();
        echo "   Transaction rolled back: " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // 2. Транзакция с откатом
    echo "2. Transaction with rollback:" . PHP_EOL;

    try {
        $connection->beginTransaction();

        $user = new User();
        $user->name = 'Will be rolled back';
        $user->email = 'rollback@example.com';
        $user->balance = 500;
        $user->asyncSave();

        echo "   User created with ID: {$user->id}" . PHP_EOL;

        // Симулируем ошибку
        throw new \RuntimeException('Simulated error');

        $connection->commit();
    } catch (\Throwable $e) {
        $connection->rollBack();
        echo "   Transaction rolled back: " . $e->getMessage() . PHP_EOL;

        // Проверяем, что пользователь не был создан
        $check = User::async()->where('email', 'rollback@example.com')->first();
        echo "   User exists after rollback: " . ($check ? 'yes' : 'no') . PHP_EOL;
    }
    echo PHP_EOL;

    // 3. Перевод средств между пользователями (классический пример)
    echo "3. Money transfer between users:" . PHP_EOL;

    // Создаем двух пользователей
    $alice = new User();
    $alice->name = 'Alice';
    $alice->email = 'alice@example.com';
    $alice->balance = 1000;
    $alice->asyncSave();

    $bob = new User();
    $bob->name = 'Bob';
    $bob->email = 'bob@example.com';
    $bob->balance = 500;
    $bob->asyncSave();

    echo "   Initial balances: Alice={$alice->balance}, Bob={$bob->balance}" . PHP_EOL;

    // Перевод 100 от Alice к Bob
    $transferAmount = 100;

    try {
        $connection->beginTransaction();

        // Списываем у Alice
        $alice->balance -= $transferAmount;
        if ($alice->balance < 0) {
            throw new \RuntimeException('Insufficient funds');
        }
        $alice->asyncSave();

        // Зачисляем Bob
        $bob->balance += $transferAmount;
        $bob->asyncSave();

        // Записываем транзакцию
        $tx = new Transaction();
        $tx->from_user_id = $alice->id;
        $tx->to_user_id = $bob->id;
        $tx->amount = $transferAmount;
        $tx->status = 'completed';
        $tx->asyncSave();

        $connection->commit();

        echo "   Transfer completed successfully" . PHP_EOL;
        echo "   Final balances: Alice={$alice->balance}, Bob={$bob->balance}" . PHP_EOL;
    } catch (\Throwable $e) {
        $connection->rollBack();
        echo "   Transfer failed: " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // 4. Параллельные транзакции в разных корутинах
    echo "4. Concurrent transactions:" . PHP_EOL;

    $wg = new Coroutine\WaitGroup();
    $results = [];

    for ($i = 0; $i < 5; $i++) {
        $wg->add();

        Coroutine::create(function () use ($i, $wg, &$results, $connection) {
            try {
                // Каждая корутина получает свое соединение из пула
                $conn = User::getConnectionResolver();
                $conn->beginTransaction();

                $user = new User();
                $user->name = "Concurrent User {$i}";
                $user->email = "concurrent{$i}@example.com";
                $user->balance = 100 * ($i + 1);
                $user->asyncSave();

                // Симулируем некоторую работу
                Coroutine::sleep(0.01);

                $conn->commit();

                $results[$i] = "success";
            } catch (\Throwable $e) {
                $conn->rollBack();
                $results[$i] = "failed: " . $e->getMessage();
            } finally {
                $wg->done();
            }
        });
    }

    $wg->wait();

    foreach ($results as $i => $result) {
        echo "   Transaction {$i}: {$result}" . PHP_EOL;
    }
    echo PHP_EOL;

    // 5. Вложенные транзакции (savepoints)
    echo "5. Nested transactions:" . PHP_EOL;

    try {
        $connection->beginTransaction();
        echo "   Started outer transaction" . PHP_EOL;

        $user1 = new User();
        $user1->name = 'Outer User';
        $user1->email = 'outer@example.com';
        $user1->asyncSave();
        echo "   Created outer user" . PHP_EOL;

        try {
            $connection->beginTransaction();
            echo "   Started inner transaction" . PHP_EOL;

            $user2 = new User();
            $user2->name = 'Inner User';
            $user2->email = 'inner@example.com';
            $user2->asyncSave();
            echo "   Created inner user" . PHP_EOL;

            // Откатываем внутреннюю транзакцию
            $connection->rollBack();
            echo "   Rolled back inner transaction" . PHP_EOL;
        } catch (\Throwable $e) {
            $connection->rollBack();
            echo "   Inner transaction error: " . $e->getMessage() . PHP_EOL;
        }

        $connection->commit();
        echo "   Committed outer transaction" . PHP_EOL;

        // Проверяем результаты
        $outer = User::async()->where('email', 'outer@example.com')->first();
        $inner = User::async()->where('email', 'inner@example.com')->first();

        echo "   Outer user exists: " . ($outer ? 'yes' : 'no') . PHP_EOL;
        echo "   Inner user exists: " . ($inner ? 'yes' : 'no') . PHP_EOL;
    } catch (\Throwable $e) {
        $connection->rollBack();
        echo "   Outer transaction error: " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // Закрытие соединения
    $connection->disconnect();

    echo "=== Example completed ===" . PHP_EOL;
});
