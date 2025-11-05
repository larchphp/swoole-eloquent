# Contributing to Swoole-Eloquent

Спасибо за интерес к проекту! Мы приветствуем вклад от сообщества.

## Как внести вклад

### 1. Создание Issue

Перед началом работы создайте Issue для обсуждения:

- **Bug Report**: Опишите проблему, шаги воспроизведения, ожидаемое поведение
- **Feature Request**: Опишите желаемую функциональность и use case
- **Question**: Задайте вопрос о библиотеке

### 2. Форк и клонирование

```bash
# Форкните репозиторий через GitHub
git clone https://github.com/your-username/swoole-eloquent.git
cd swoole-eloquent

# Установите зависимости
composer install
```

### 3. Создание feature ветки

```bash
# Переключитесь на develop
git checkout develop

# Создайте feature ветку
git checkout -b feature/your-feature-name
```

### 4. Разработка

#### Стиль кода

- Используйте **PSR-12** coding standard
- Добавляйте type hints для всех параметров и возвращаемых значений
- Документируйте публичные методы с помощью PHPDoc

Пример:

```php
/**
 * Выполнить SELECT запрос
 *
 * @param string $query SQL запрос
 * @param array $bindings Параметры привязки
 * @return array Массив результатов
 */
public function select(string $query, array $bindings = []): array
{
    // Implementation
}
```

#### Структура коммитов

Следуйте принципу **один коммит = одно изменение**:

```bash
# ✅ Хорошо
git commit -m "Add connection pool health check feature"
git commit -m "Fix memory leak in transaction rollback"

# ❌ Плохо
git commit -m "Various fixes and improvements"
git commit -m "WIP"
```

#### Формат сообщений коммитов

Используйте **одно предложение** в повелительном наклонении:

```
Add async model support for relationships
Fix transaction rollback in nested transactions
Update documentation with performance benchmarks
```

### 5. Тестирование

Всегда добавляйте тесты для нового функционала:

```bash
# Запустите все тесты
composer test

# Запустите конкретный тест
vendor/bin/phpunit tests/Unit/AsyncModelTest.php

# Проверьте покрытие кода
composer test-coverage
```

#### Типы тестов

1. **Unit тесты** (`tests/Unit/`) - тестируют отдельные классы
2. **Integration тесты** (`tests/Integration/`) - тестируют взаимодействие с реальной БД

Пример unit теста:

```php
public function testAsyncSaveCreatesNewUser(): void
{
    $user = new User();
    $user->name = 'Test User';
    $user->email = 'test@example.com';

    $result = $user->asyncSave();

    $this->assertTrue($result);
    $this->assertTrue($user->exists);
    $this->assertNotNull($user->id);
}
```

### 6. Документация

Обновите документацию если изменили API:

- **README.md** - для основных изменений
- **docs/architecture.md** - для архитектурных изменений
- **examples/** - добавьте примеры использования

### 7. Pull Request

#### Перед созданием PR

```bash
# Убедитесь что тесты проходят
composer test

# Проверьте код стиль
composer stan

# Обновите с develop
git checkout develop
git pull origin develop
git checkout feature/your-feature-name
git rebase develop
```

#### Создание PR

1. Push в ваш форк:
```bash
git push origin feature/your-feature-name
```

2. Создайте Pull Request в GitHub:
    - **Base**: `develop`
    - **Head**: `your-fork:feature/your-feature-name`

3. Заполните шаблон PR:

```markdown
## Описание
Кратко опишите изменения

## Мотивация и контекст
Зачем это нужно? Какую проблему решает?

## Как протестировано
- [ ] Unit тесты добавлены/обновлены
- [ ] Integration тесты добавлены/обновлены
- [ ] Тесты проходят локально
- [ ] Документация обновлена

## Тип изменения
- [ ] Bug fix (не ломающее изменение)
- [ ] New feature (не ломающее изменение)
- [ ] Breaking change (изменение API)
- [ ] Documentation update

## Checklist
- [ ] Код следует PSR-12
- [ ] Добавлены тесты
- [ ] Все тесты проходят
- [ ] Документация обновлена
- [ ] Коммиты имеют понятные сообщения
```

### 8. Code Review

После создания PR:

1. Дождитесь автоматических проверок (CI)
2. Ответьте на комментарии reviewer'ов
3. Внесите запрошенные изменения
4. После одобрения PR будет смержен в `develop`

## Git Workflow

Мы используем **Git Flow** подход:

```
main (master)
  ↑
  └── develop (публичная ветка)
        ↑
        ├── feature/async-model
        ├── feature/mysql-support
        └── feature/relationships
```

### Ветки

- **main** - стабильные релизы
- **develop** - основная ветка разработки (публичная)
- **feature/** - новые возможности
- **bugfix/** - исправления багов
- **hotfix/** - срочные исправления для production

### Процесс

1. Создайте feature ветку из `develop`
2. Регулярно коммитьте изменения
3. Создайте PR в `develop`
4. После merge удалите feature ветку

```bash
git checkout develop
git pull origin develop
git branch -d feature/your-feature-name
```

## Архитектурные принципы

При разработке следуйте этим принципам:

### 1. SOLID

- **Single Responsibility**: Один класс = одна ответственность
- **Open/Closed**: Открыт для расширения, закрыт для модификации
- **Liskov Substitution**: Подклассы должны работать вместо базовых классов
- **Interface Segregation**: Много мелких интерфейсов лучше одного большого
- **Dependency Inversion**: Зависимость от абстракций, не от конкреций

### 2. Асинхронность

- Все IO операции должны быть неблокирующими
- Используйте Swoole корутины для параллельных операций
- Избегайте блокирующих вызовов (file_get_contents, sleep, etc.)

### 3. Производительность

- Минимизируйте копирование больших объектов
- Используйте пул соединений эффективно
- Профилируйте критичные участки кода

### 4. Безопасность

- Всегда используйте prepared statements
- Никогда не доверяйте пользовательскому вводу
- Экранируйте специальные символы

## Что нужно проекту

### High Priority

- [ ] Поддержка MySQL через `Swoole\Coroutine\MySQL`
- [ ] Поддержка Relationships (hasMany, belongsTo, etc.)
- [ ] Query Builder для сложных запросов (joins, subqueries)
- [ ] Connection factory для легкой настройки
- [ ] Middleware для HTTP сервера

### Medium Priority

- [ ] Поддержка Redis для кэширования
- [ ] Events и Observers для моделей
- [ ] Soft deletes
- [ ] Scope support
- [ ] Eager loading оптимизация

### Low Priority

- [ ] Laravel Service Provider
- [ ] CLI команды для миграций
- [ ] Query logging и debugging
- [ ] Prometheus метрики

## Вопросы?

- Создайте Issue с меткой `question`
- Напишите в Discussions
- Свяжитесь с мейнтейнерами

## Кодекс поведения

Будьте уважительны к другим участникам сообщества:

- Используйте дружелюбный язык
- Принимайте конструктивную критику
- Фокусируйтесь на том, что лучше для проекта
- Проявляйте эмпатию к другим участникам

---

Спасибо за ваш вклад! 🎉
