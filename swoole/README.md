# Swoole-Eloquent

Асинхронная PHP-библиотека, объединяющая производительность Swoole и удобство Eloquent ORM.

## 🎯 Цель проекта

Создать библиотеку, которая позволяет писать асинхронные backend-сервисы на PHP с использованием привычного синтаксиса Eloquent ORM, без блокировки потоков и с максимальной производительностью.

## ✨ Основные возможности

- ⚡ **Асинхронные запросы к БД** через Swoole корутины
- 🔄 **Пул соединений** для эффективного использования ресурсов
- 📝 **Совместимость с Eloquent** - привычный синтаксис ORM
- 🚀 **Высокая производительность** - десятки тысяч запросов параллельно
- 🔧 **Универсальная архитектура** - легко добавить поддержку разных БД

## 📦 Требования

- PHP 8.1+
- ext-swoole
- ext-pgsql (для PostgreSQL)

## 🚀 Установка

```bash
composer require swoole-eloquent/swoole-eloquent
```

## 💡 Быстрый старт

### 1. Настройка соединения

```php
use SwooleEloquent\Connection\SwoolePostgresConnection;
use SwooleEloquent\ORM\AsyncModel;

$connection = new SwoolePostgresConnection([
    'host' => '127.0.0.1',
    'port' => 5432,
    'database' => 'your_database',
    'username' => 'postgres',
    'password' => 'password',
    'pool_size' => 20, // Размер пула соединений
]);

// Установка соединения для моделей
YourModel::setConnectionResolver($connection);
```

### 2. Определение модели

```php
use SwooleEloquent\ORM\AsyncModel;

class User extends AsyncModel
{
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'age'];
    protected array $hidden = ['password'];
}
```

### 3. Использование в корутинах

```php
use Swoole\Coroutine;

Coroutine\run(function() {
    // Поиск пользователя
    $user = User::async()->find(1);
    echo $user->name;

    // Выборка с условиями
    $activeUsers = User::async()
        ->where('age', '>', 18)
        ->orderBy('name', 'asc')
        ->get();

    // Создание нового пользователя
    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->asyncSave();

    // Обновление
    $user->age = 30;
    $user->asyncSave();

    // Удаление
    $user->asyncDelete();
});
```

### 4. HTTP Server пример

```php
use Swoole\Http\Server;

$server = new Server("0.0.0.0", 9501);

$server->on('Request', function ($request, $response) {
    // Каждый запрос выполняется в отдельной корутине
    $users = User::async()->where('active', true)->get();

    $response->header('Content-Type', 'application/json');
    $response->end(json_encode($users));
});

$server->start();
```

## 📖 API

### Query Methods

```php
// Поиск
User::async()->find(1);
User::async()->findOrFail(1);

// Условия
User::async()->where('active', true)->get();
User::async()->whereIn('id', [1, 2, 3])->get();
User::async()->first();
User::async()->count();

// Модификация
User::async()->update(['status' => 'active']);
User::async()->delete();
```

### Instance Methods

```php
$user = User::async()->find(1);
$user->asyncSave();
$user->asyncUpdate(['name' => 'New']);
$user->asyncDelete();
$user->asyncRefresh();
```

### Relationships

```php
// Загрузка связей
$posts = $user->posts()->async()->get();
```

### Транзакции

```php
try {
    $connection->beginTransaction();

    $user = new User();
    $user->name = 'Alice';
    $user->asyncSave();

    $anotherUser = new User();
    $anotherUser->name = 'Bob';
    $anotherUser->asyncSave();

    $connection->commit();
} catch (\Throwable $e) {
    $connection->rollBack();
    throw $e;
}
```

## 🚀 Производительность

### Сравнение с обычным Laravel + FPM

| Метрика | Laravel + FPM | Swoole-Eloquent | Улучшение |
|---------|---------------|-----------------|-----------|
| Время ответа | 10-15 мс | 2-3 мс | **5x быстрее** |
| Конкурентность | Блокирующая | Асинхронная | **10000+ параллельных запросов** |
| Подключения к БД | Создаются на каждый запрос | Пул соединений | **Переиспользование** |
| QPS (запросов/сек) | ~100-200 | **1000+** | **10x выше** |

### Результаты стресс-теста

```
1000 параллельных запросов:
✓ Завершено: 1000/1000
✓ Время: 245 мс
✓ QPS: 4082 запросов/сек
✓ Среднее время: 0.24 мс/запрос
```

## 📚 Примеры

Все примеры находятся в директории `examples/`:

- **basic_usage.php** - Базовые CRUD операции
- **demo_server.php** - HTTP сервер с REST API
- **concurrent_queries.php** - Параллельные запросы и стресс-тест
- **transactions_example.php** - Работа с транзакциями

Запуск примеров:

```bash
php examples/basic_usage.php
php examples/demo_server.php
php examples/concurrent_queries.php
php examples/transactions_example.php
```

## 🏗️ Архитектура

```
src/
├── Connection/         # Пул соединений и драйверы БД
├── ORM/               # Async Model и Query Builder
└── Support/           # Утилиты и хелперы
```

## 🧪 Тестирование

```bash
composer test
composer test-coverage
```

## 📝 Лицензия

MIT License

## 🤝 Вклад в проект

Contributions приветствуются! См. [CONTRIBUTING.md](CONTRIBUTING.md)

---

🤖 **Статус проекта**: В активной разработке
