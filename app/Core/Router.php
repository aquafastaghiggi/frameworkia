<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    protected array $routes = [];

    public function get(string $uri, callable|array $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    public function addTerminalRoutes(): void
    {
        $this->post('/workspace/terminal/execute', [App\Http\Controllers\TerminalController::class, 'execute']);
    }

    protected function addRoute(string $method, string $uri, callable|array $action): void
    {
        $this->routes[$method][$uri] = $action;
    }

    public function dispatch(Request $request, Response $response, View $view, Logger $logger): void
    {
        $method = $request->method();
        $uri = $request->uri();

        $action = $this->routes[$method][$uri] ?? null;

        if ($action === null) {
            $response->html('<h1>404 - Página não encontrada</h1>', 404);
            return;
        }

        if (is_callable($action)) {
            $result = $action($request, $response, $view);

            if (is_string($result)) {
                $response->html($result);
            }

            return;
        }

        if (is_array($action) && count($action) === 2) {
            [$controllerClass, $methodName] = $action;

            $controller = new $controllerClass($view, $response, $logger);

            if (!method_exists($controller, $methodName)) {
                throw new \RuntimeException("Método {$methodName} não existe no controller {$controllerClass}");
            }

            $controller->$methodName($request);
            return;
        }

        throw new \RuntimeException('Rota inválida');
    }
}