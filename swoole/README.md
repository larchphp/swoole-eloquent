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

```php
use Swoole\Coroutine;
use SwooleEloquent\ORM\AsyncModel;

// Определение модели
class User extends AsyncModel
{
    protected $table = 'users';
    protected $fillable = ['name', 'email'];
}

// Использование в Swoole корутине
Coroutine\run(function() {
    // Поиск пользователя
    $user = User::async()->find(1);

    // Выборка с условиями
    $activeUsers = User::async()
        ->where('active', true)
        ->get();

    // Сохранение
    $user->name = 'New Name';
    $user->asyncSave();
});
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
