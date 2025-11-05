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
    protected array $fillable = ['name', 'email', 'age'];
}

// Запуск в корутине
Coroutine\run(function () {
    // Настройка соединения с БД
    $connection = new SwoolePostgresConnection([
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'test_db',
        'username' => 'postgres',
        'password' => 'password',
        'pool_size' => 10,
    ]);

    // Установка соединения для моделей
    User::setConnectionResolver($connection);

    echo "=== Swoole-Eloquent Basic Usage Example ===" . PHP_EOL . PHP_EOL;

    // 1. Поиск пользователя по ID
    echo "1. Find user by ID:" . PHP_EOL;
    $user = User::async()->find(1);
    if ($user) {
        echo "   Found: {$user->name} ({$user->email})" . PHP_EOL;
    } else {
        echo "   User not found" . PHP_EOL;
    }
    echo PHP_EOL;

    // 2. Выборка с условиями
    echo "2. Get users where age > 25:" . PHP_EOL;
    $users = User::async()
        ->where('age', '>', 25)
        ->orderBy('name', 'asc')
        ->get();

    echo "   Found " . count($users) . " users:" . PHP_EOL;
    foreach ($users as $u) {
        echo "   - {$u->name} (age: {$u->age})" . PHP_EOL;
    }
    echo PHP_EOL;

    // 3. Создание нового пользователя
    echo "3. Create new user:" . PHP_EOL;
    $newUser = new User();
    $newUser->name = 'John Doe';
    $newUser->email = 'john@example.com';
    $newUser->age = 30;

    if ($newUser->asyncSave()) {
        echo "   User created with ID: {$newUser->id}" . PHP_EOL;
    } else {
        echo "   Failed to create user" . PHP_EOL;
    }
    echo PHP_EOL;

    // 4. Обновление пользователя
    echo "4. Update user:" . PHP_EOL;
    if ($newUser->exists) {
        $newUser->age = 31;
        if ($newUser->asyncSave()) {
            echo "   User updated successfully" . PHP_EOL;
        }
    }
    echo PHP_EOL;

    // 5. Массовое обновление
    echo "5. Bulk update:" . PHP_EOL;
    $affected = User::async()
        ->where('age', '<', 18)
        ->update(['status' => 'minor']);
    echo "   Updated {$affected} users" . PHP_EOL;
    echo PHP_EOL;

    // 6. Подсчет записей
    echo "6. Count users:" . PHP_EOL;
    $count = User::async()->count();
    echo "   Total users: {$count}" . PHP_EOL;
    echo PHP_EOL;

    // 7. Удаление пользователя
    echo "7. Delete user:" . PHP_EOL;
    if ($newUser->exists && $newUser->asyncDelete()) {
        echo "   User deleted successfully" . PHP_EOL;
    }
    echo PHP_EOL;

    // 8. Использование whereIn
    echo "8. Get users with IDs in [1, 2, 3]:" . PHP_EOL;
    $selectedUsers = User::async()
        ->whereIn('id', [1, 2, 3])
        ->get();
    echo "   Found " . count($selectedUsers) . " users" . PHP_EOL;
    echo PHP_EOL;

    // 9. First or Fail
    echo "9. Find or fail:" . PHP_EOL;
    try {
        $user = User::async()->findOrFail(999);
        echo "   User found: {$user->name}" . PHP_EOL;
    } catch (\RuntimeException $e) {
        echo "   " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // Закрытие соединения
    $connection->disconnect();

    echo "=== Example completed ===" . PHP_EOL;
});
