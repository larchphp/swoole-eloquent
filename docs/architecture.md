# Архитектура Swoole-Eloquent

## Обзор

Swoole-Eloquent - это библиотека, которая служит адаптером между асинхронным Swoole и синхронным интерфейсом Eloquent ORM, позволяя выполнять неблокирующие запросы к базе данных с сохранением привычного API.

## Структура проекта

```
swoole-eloquent/
├── src/
│   ├── Connection/           # Слой работы с соединениями
│   │   ├── DatabaseDriverInterface.php
│   │   ├── AbstractSwooleConnection.php
│   │   ├── SwoolePostgresConnection.php
│   │   └── SwoolePostgresPool.php
│   ├── ORM/                  # Слой ORM
│   │   ├── AsyncModel.php
│   │   └── AsyncQueryBuilder.php
│   └── Support/              # Вспомогательные утилиты
│       ├── CoroutineContext.php
│       └── Helpers.php
├── examples/                 # Примеры использования
├── tests/                    # Тесты
└── docs/                     # Документация
```

## Слои библиотеки

### 1. Connection Layer (Слой соединений)

#### DatabaseDriverInterface

**Назначение**: Универсальный интерфейс для различных драйверов БД (PostgreSQL, MySQL, и т.д.)

**Ключевые методы**:
- `select()`, `insert()`, `update()`, `delete()` - CRUD операции
- `beginTransaction()`, `commit()`, `rollBack()` - управление транзакциями
- `isAlive()`, `reconnect()` - управление соединением

**Преимущества**:
- Легко добавить поддержку новых БД
- Единообразный интерфейс для всех драйверов
- Возможность тестирования через mock-объекты

#### SwoolePostgresPool

**Назначение**: Управление пулом соединений PostgreSQL

**Архитектурные решения**:
```
┌─────────────────────────────┐
│   SwoolePostgresPool        │
├─────────────────────────────┤
│ - Channel (размер N)        │
│ - Метрики (active/idle)     │
│ - Auto-reconnect            │
│ - Health checks             │
└─────────────────────────────┘
         ↓
    Swoole\Coroutine\Channel
         ↓
┌───┬───┬───┬───┬───┬───┬───┐
│PG │PG │PG │PG │PG │PG │...│  ← Пул соединений
└───┴───┴───┴───┴───┴───┴───┘
```

**Как работает**:

1. **Инициализация**: Создается `N` соединений и помещаются в `Coroutine\Channel`
2. **Получение соединения**: `get()` извлекает соединение из канала (блокируется если пусто)
3. **Возврат соединения**: `put()` возвращает соединение обратно в канал
4. **Проверка здоровья**: Перед возвратом проверяется `SELECT 1`
5. **Транзакции**: Соединение удерживается до завершения транзакции

**Метрики**:
```php
[
    'size' => 20,        // Размер пула
    'active' => 5,       // Активных соединений
    'idle' => 15,        // Свободных соединений
    'waiting' => 2,      // Ожидающих запросов
]
```

#### AbstractSwooleConnection

**Назначение**: Базовый класс, адаптирующий `Illuminate\Database\Connection` для работы с асинхронными драйверами

**Ключевые адаптации**:

1. **Переопределение методов PDO**:
```php
// Вместо PDO используем async драйвер
public function select($query, $bindings = []): array
{
    return $this->driver->select($query, $this->prepareBindings($bindings));
}
```

2. **Управление транзакциями**:
```php
private int $transactionLevel = 0;

public function beginTransaction(): void
{
    if ($this->transactionLevel === 0) {
        $this->driver->beginTransaction();
    }
    $this->transactionLevel++;
}
```

3. **Совместимость с Eloquent**:
- Возвращает `null` вместо PDO объектов
- Логирование запросов
- Обработка биндингов

#### SwoolePostgresConnection

**Назначение**: Конкретная реализация для PostgreSQL

**Компоненты**:

1. **SwoolePostgresDriver** - реализация `DatabaseDriverInterface`
2. **Интеграция с пулом** - использует `SwoolePostgresPool`
3. **PostgreSQL грамматика** - `PostgresGrammar`, `PostgresProcessor`

**Особенности**:
```php
// Для транзакций соединение удерживается
private ?PostgreSQL $transactionConnection = null;

public function beginTransaction(): bool
{
    $this->transactionConnection = $this->pool->get();
    return $this->transactionConnection->query('BEGIN');
}
```

### 2. ORM Layer (Слой ORM)

#### AsyncModel

**Назначение**: Базовый класс модели с асинхронным интерфейсом

**Архитектура**:

```
       AsyncModel
            │
    ┌───────┴───────┐
    │               │
 Атрибуты      Методы
    │               │
┌───┴───┐      ┌────┴────┐
│original│      │  async()│
│current │      │asyncSave│
└────────┘      └─────────┘
```

**Ключевые концепции**:

1. **Отслеживание изменений**:
```php
protected array $attributes = [];  // Текущие значения
protected array $original = [];    // Оригинальные значения

public function getDirty(): array  // Измененные атрибуты
{
    return array_diff_assoc($this->attributes, $this->original);
}
```

2. **Массовое заполнение**:
```php
protected array $fillable = ['name', 'email'];

public function fill(array $attributes): self
{
    foreach ($attributes as $key => $value) {
        if ($this->isFillable($key)) {
            $this->setAttribute($key, $value);
        }
    }
}
```

3. **Async операции**:
```php
public function asyncSave(): bool
{
    if ($this->exists) {
        // UPDATE
        return $this->performUpdate();
    } else {
        // INSERT
        return $this->performInsert();
    }
}
```

#### AsyncQueryBuilder

**Назначение**: Построитель запросов с асинхронным выполнением

**Архитектура**:

```
User::async()
    │
    ↓ создает
AsyncQueryBuilder
    │
    ├── where()
    ├── orderBy()
    ├── limit()
    │
    ↓ выполняет
Illuminate\Database\Query\Builder
    │
    ↓ через
SwoolePostgresConnection
    │
    ↓ использует
SwoolePostgresPool
```

**Ключевые методы**:

1. **Построение запроса**:
```php
public function where($column, $operator, $value): self
{
    $this->builder->where($column, $operator, $value);
    return $this; // Chainable
}
```

2. **Выполнение**:
```php
public function get(array $columns = ['*']): array
{
    $results = $this->builder->get($columns);

    // Гидратация моделей
    return array_map(
        fn($result) => $this->hydrateModel($result),
        $results->all()
    );
}
```

3. **Гидратация**:
```php
protected function hydrateModel(object $result): object
{
    if ($this->model === null) {
        return $result;
    }

    $instance = new $this->model();
    foreach ((array) $result as $key => $value) {
        $instance->{$key} = $value;
    }
    $instance->syncOriginal();

    return $instance;
}
```

### 3. Support Layer (Вспомогательный слой)

#### CoroutineContext

**Назначение**: Управление контекстом в рамках одной корутины

**Проблема**: Каждая корутина должна иметь изолированный контекст (например, ID пользователя, настройки)

**Решение**:
```php
// Хранилище: [coroutine_id => [key => value]]
private static array $contexts = [];

public static function put(string $key, mixed $value): void
{
    $cid = Coroutine::getCid();
    self::$contexts[$cid][$key] = $value;
}

public static function get(string $key, mixed $default = null): mixed
{
    $cid = Coroutine::getCid();
    return self::$contexts[$cid][$key] ?? $default;
}
```

**Автоочистка**:
```php
public static function withContext(callable $callback): mixed
{
    try {
        return $callback();
    } finally {
        self::clear(); // Автоматически очищаем контекст
    }
}
```

#### Helpers

**Назначение**: Утилиты для работы с типами, логированием, бенчмарками

**Основные функции**:

1. **Конвертация типов PostgreSQL ↔ PHP**
2. **Логирование с временными метками**
3. **Бенчмаркинг**
4. **Retry механизм**

## Потоки данных

### Запрос SELECT

```
User::async()->find(1)
        ↓
AsyncQueryBuilder::find()
        ↓
AsyncQueryBuilder::getBuilder()->where('id', 1)->first()
        ↓
AbstractSwooleConnection::select()
        ↓
SwoolePostgresDriver::select()
        ↓
SwoolePostgresPool::get()  ← Получаем соединение
        ↓
PostgreSQL::query()        ← Выполняем запрос
        ↓
SwoolePostgresPool::put()  ← Возвращаем соединение
        ↓
AsyncQueryBuilder::hydrateModel()
        ↓
User объект
```

### Запрос INSERT

```
$user = new User();
$user->name = 'John';
$user->asyncSave()
        ↓
AsyncModel::asyncSave()
        ↓
AbstractSwooleConnection::table()->insertGetId()
        ↓
SwoolePostgresDriver::insert()
        ↓
[Аналогично SELECT потоку]
        ↓
ID последней записи
        ↓
Установка $user->id и $user->exists = true
```

### Транзакция

```
$connection->beginTransaction()
        ↓
SwoolePostgresDriver::beginTransaction()
        ↓
$this->transactionConnection = $pool->get()  ← Соединение закрепляется
        ↓
[Выполнение запросов с использованием transactionConnection]
        ↓
$connection->commit()
        ↓
$pool->put($this->transactionConnection)    ← Соединение освобождается
$this->transactionConnection = null
```

## Многопоточность и безопасность

### Корутины vs Потоки

**Swoole корутины**:
- Легковесные (stackless coroutines)
- Переключение контекста на IO операциях
- Не требуют блокировок для переменных внутри корутины

**Безопасность пула**:
```php
// Channel обеспечивает безопасность между корутинами
$this->pool = new Channel($size);  // Thread-safe
```

### Изоляция данных

1. **Пул соединений** - общий для всех корутин
2. **Модели** - создаются для каждого запроса
3. **CoroutineContext** - изолирован по ID корутины
4. **Транзакции** - соединение закрепляется за корутиной

## Производительность

### Оптимизации

1. **Пул соединений**:
   - Избегаем overhead создания соединения (~10-50ms)
   - Переиспользуем существующие соединения

2. **Корутины**:
   - Переключение контекста < 1μs
   - 10000+ параллельных корутин на 1 процесс

3. **Memory footprint**:
   - Корутина: ~2KB
   - Соединение: ~50KB
   - Пул из 20 соединений: ~1MB

### Узкие места

1. **Размер пула**: Слишком маленький → ожидание соединения
2. **Размер пула**: Слишком большой → нагрузка на БД
3. **Длинные запросы**: Блокируют соединение из пула

### Рекомендации

```php
// Для API с короткими запросами
'pool_size' => worker_num * 5

// Для API с аналитикой
'pool_size' => worker_num * 10

// Для mixed workload
'pool_size' => worker_num * 3
```

## Расширение библиотеки

### Добавление нового драйвера БД

1. Реализовать `DatabaseDriverInterface`
2. Создать пул соединений (аналог `SwoolePostgresPool`)
3. Создать класс Connection (аналог `SwoolePostgresConnection`)

Пример для MySQL:

```php
class SwooleMysqlConnection extends AbstractSwooleConnection
{
    public function __construct(array $config)
    {
        $pool = new SwooleM ysqlPool($config);
        $driver = new SwooleMysqlDriver($pool);

        parent::__construct($driver, $config['database'], ...);
    }
}
```

### Добавление Relationships

Расширить `AsyncModel` для поддержки связей:

```php
public function hasMany(string $related, string $foreignKey = null)
{
    return new AsyncHasMany($this, $related, $foreignKey);
}
```

## Лучшие практики

1. **Размер пула**: Настраивайте под нагрузку
2. **Транзакции**: Держите их короткими
3. **Контекст**: Используйте `CoroutineContext::withContext()`
4. **Memory leaks**: Очищайте большие объекты после использования
5. **Error handling**: Всегда обрабатывайте исключения в транзакциях

## Ссылки

- [Swoole Docs](https://www.swoole.co.uk/docs/)
- [Laravel Database Docs](https://laravel.com/docs/database)
- [PostgreSQL Async](https://www.postgresql.org/docs/current/libpq-async.html)
