<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewares = [];

    public function __construct()
    {
        $this->registerDefaultMiddlewares();
    }

    private function registerDefaultMiddlewares(): void
    {
        // Проверка авторизации
        $this->middlewares['auth'] = function (Request $req) {
            if (!Auth::check()) {
                if ($req->isAjax()) {
                    Response::error('Авторизация қажет / Требуется авторизация', 401);
                }
                Response::redirect('/login');
            }
        };

        // Доступ только для редактора или администратора
        $this->middlewares['editor'] = function (Request $req) {
            if (!Auth::isEditor()) {
                if ($req->isAjax()) {
                    Response::error('Өңдеуге құқық жоқ / Недостаточно прав для редактирования', 403);
                }
                Response::redirect('/?error=forbidden');
            }
        };

        // Доступ только для администратора (Жандос)
        $this->middlewares['admin'] = function (Request $req) {
            if (!Auth::isAdmin()) {
                if ($req->isAjax()) {
                    Response::error('Тек әкімшіге рұқсат / Доступно только администратору', 403);
                }
                Response::redirect('/?error=admin_only');
            }
        };
    }

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, mixed $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => '/' . trim($path, '/'),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $uri = '/' . trim($request->getUri(), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#u';

            if (preg_match($pattern, $uri, $matches)) {
                // Извлекаем только именованные параметры
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Выполняем middleware
                foreach ($route['middleware'] as $mwName) {
                    if (isset($this->middlewares[$mwName])) {
                        call_user_func($this->middlewares[$mwName], $request);
                    }
                }

                try {
                    $this->executeHandler($route['handler'], $request, $params);
                } catch (\Throwable $e) {
                    Logger::error("Unhandled error on {$method} {$uri}: " . $e->getMessage(), $e);
                    if ($request->isAjax() || str_starts_with($uri, '/api/')) {
                        Response::json([
                            'status' => 'error',
                            'error' => 'Серверде қате орын алды / Внутренняя ошибка сервера',
                            'message' => Config::isDebug() ? $e->getMessage() : 'Жүйелік қате. storage/logs/error.log файлын қараңыз.',
                        ], 500);
                        return;
                    }
                    http_response_code(500);
                    echo "<div style='font-family:sans-serif;padding:30px;max-width:700px;margin:40px auto;border:1px solid #f5c6cb;border-radius:8px;background:#f8d7da;color:#721c24;'>";
                    echo "<h2>500 - Сервер қатесі / Ошибка сервера</h2>";
                    echo "<p>" . htmlspecialchars(Config::isDebug() ? $e->getMessage() : 'Жүйеде қате орын алды. Қате журналына жазылды.') . "</p>";
                    if (Config::isDebug()) {
                        echo "<pre style='background:#fff;padding:15px;overflow:auto;border-radius:4px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                    }
                    echo "</div>";
                    return;
                }
                return;
            }
        }

        // Маршрут не найден (404)
        if ($request->isAjax()) {
            Response::error('Бет табылмады / Маршрут не найден', 404);
        } else {
            http_response_code(404);
            echo "<h1>404 - Бет табылмады / Страница не найдена</h1>";
            exit;
        }
    }

    private function executeHandler(mixed $handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $request, ...array_values($params));
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$controllerClass, $method] = explode('@', $handler, 2);
            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Контроллер не найден: {$controllerClass}");
            }

            $controller = new $controllerClass();
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Метод {$method} не найден в контроллере {$controllerClass}");
            }

            call_user_func([$controller, $method], $request, ...array_values($params));
            return;
        }

        throw new \RuntimeException("Некорректный обработчик маршрута");
    }
}
