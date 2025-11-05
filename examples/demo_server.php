<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
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
    protected array $hidden = ['password'];
}

// Создание HTTP сервера
$server = new Server("0.0.0.0", 9501);

$server->set([
    'worker_num' => 4,
    'enable_coroutine' => true,
    'max_coroutine' => 10000,
]);

// Инициализация при старте воркера
$server->on('WorkerStart', function (Server $server, int $workerId) {
    echo "Worker #{$workerId} started" . PHP_EOL;

    // Настройка соединения с БД для каждого воркера
    $connection = new SwoolePostgresConnection([
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 5432),
        'database' => getenv('DB_NAME') ?: 'test_db',
        'username' => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: 'password',
        'pool_size' => 20,
    ]);

    User::setConnectionResolver($connection);
});

// Обработка запросов
$server->on('Request', function (Request $request, Response $response) {
    $response->header('Content-Type', 'application/json');
    $response->header('Server', 'Swoole-Eloquent/1.0');

    try {
        $uri = $request->server['request_uri'];
        $method = $request->server['request_method'];

        Helpers::log("Request: {$method} {$uri}");

        // Роутинг
        if ($method === 'GET' && $uri === '/users') {
            // GET /users - список пользователей
            handleGetUsers($request, $response);
        } elseif ($method === 'GET' && preg_match('/^\/users\/(\d+)$/', $uri, $matches)) {
            // GET /users/{id} - получить пользователя
            handleGetUser($response, (int) $matches[1]);
        } elseif ($method === 'POST' && $uri === '/users') {
            // POST /users - создать пользователя
            handleCreateUser($request, $response);
        } elseif ($method === 'PUT' && preg_match('/^\/users\/(\d+)$/', $uri, $matches)) {
            // PUT /users/{id} - обновить пользователя
            handleUpdateUser($request, $response, (int) $matches[1]);
        } elseif ($method === 'DELETE' && preg_match('/^\/users\/(\d+)$/', $uri, $matches)) {
            // DELETE /users/{id} - удалить пользователя
            handleDeleteUser($response, (int) $matches[1]);
        } elseif ($method === 'GET' && $uri === '/health') {
            // GET /health - проверка здоровья
            handleHealth($response);
        } else {
            // 404
            $response->status(404);
            $response->end(json_encode(['error' => 'Not found']));
        }
    } catch (\Throwable $e) {
        Helpers::log("Error: " . $e->getMessage(), [], 'error');

        $response->status(500);
        $response->end(json_encode([
            'error' => 'Internal server error',
            'message' => $e->getMessage(),
        ]));
    }
});

/**
 * GET /users - список пользователей
 */
function handleGetUsers(Request $request, Response $response): void
{
    $query = User::async();

    // Поддержка фильтрации
    if (isset($request->get['age_min'])) {
        $query->where('age', '>=', (int) $request->get['age_min']);
    }

    if (isset($request->get['age_max'])) {
        $query->where('age', '<=', (int) $request->get['age_max']);
    }

    // Поддержка пагинации
    $limit = isset($request->get['limit']) ? (int) $request->get['limit'] : 10;
    $offset = isset($request->get['offset']) ? (int) $request->get['offset'] : 0;

    $users = $query
        ->orderBy('id', 'desc')
        ->limit($limit)
        ->offset($offset)
        ->get();

    $total = User::async()->count();

    $response->end(json_encode([
        'data' => array_map(fn($u) => $u->toArray(), $users),
        'meta' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ],
    ]));
}

/**
 * GET /users/{id} - получить пользователя
 */
function handleGetUser(Response $response, int $id): void
{
    $user = User::async()->find($id);

    if ($user === null) {
        $response->status(404);
        $response->end(json_encode(['error' => 'User not found']));
        return;
    }

    $response->end(json_encode(['data' => $user->toArray()]));
}

/**
 * POST /users - создать пользователя
 */
function handleCreateUser(Request $request, Response $response): void
{
    $data = json_decode($request->rawContent(), true);

    if (!isset($data['name']) || !isset($data['email'])) {
        $response->status(400);
        $response->end(json_encode(['error' => 'Name and email are required']));
        return;
    }

    $user = new User();
    $user->fill($data);

    if ($user->asyncSave()) {
        $response->status(201);
        $response->end(json_encode(['data' => $user->toArray()]));
    } else {
        $response->status(500);
        $response->end(json_encode(['error' => 'Failed to create user']));
    }
}

/**
 * PUT /users/{id} - обновить пользователя
 */
function handleUpdateUser(Request $request, Response $response, int $id): void
{
    $user = User::async()->find($id);

    if ($user === null) {
        $response->status(404);
        $response->end(json_encode(['error' => 'User not found']));
        return;
    }

    $data = json_decode($request->rawContent(), true);
    $user->fill($data);

    if ($user->asyncSave()) {
        $response->end(json_encode(['data' => $user->toArray()]));
    } else {
        $response->status(500);
        $response->end(json_encode(['error' => 'Failed to update user']));
    }
}

/**
 * DELETE /users/{id} - удалить пользователя
 */
function handleDeleteUser(Response $response, int $id): void
{
    $user = User::async()->find($id);

    if ($user === null) {
        $response->status(404);
        $response->end(json_encode(['error' => 'User not found']));
        return;
    }

    if ($user->asyncDelete()) {
        $response->status(204);
        $response->end();
    } else {
        $response->status(500);
        $response->end(json_encode(['error' => 'Failed to delete user']));
    }
}

/**
 * GET /health - проверка здоровья
 */
function handleHealth(Response $response): void
{
    $metrics = User::getConnectionResolver()->getPoolMetrics();

    $response->end(json_encode([
        'status' => 'healthy',
        'pool' => $metrics,
        'memory' => [
            'usage' => Helpers::formatBytes(memory_get_usage(true)),
            'peak' => Helpers::formatBytes(memory_get_peak_usage(true)),
        ],
    ]));
}

echo "Swoole-Eloquent Demo Server started on http://0.0.0.0:9501" . PHP_EOL;
echo "Available endpoints:" . PHP_EOL;
echo "  GET    /users       - List users" . PHP_EOL;
echo "  GET    /users/{id}  - Get user" . PHP_EOL;
echo "  POST   /users       - Create user" . PHP_EOL;
echo "  PUT    /users/{id}  - Update user" . PHP_EOL;
echo "  DELETE /users/{id}  - Delete user" . PHP_EOL;
echo "  GET    /health      - Health check" . PHP_EOL;

$server->start();
