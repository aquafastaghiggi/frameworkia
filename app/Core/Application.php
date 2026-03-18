<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use ErrorException;

class Application
{
    protected Router $router;
    protected Request $request;
    protected Response $response;
    protected View $view;
    protected Logger $logger;

    public function __construct(
        protected string $basePath
    ) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['request_id'])) {
            $_SESSION['request_id'] = bin2hex(random_bytes(8));
        }

        $this->router = new Router();
        $this->request = new Request();
        $this->response = new Response();
        $this->view = new View($this->basePath);
        $this->logger = new Logger($this->basePath);

        $this->setupErrorHandlers();
    }

    protected function setupErrorHandlers(): void
    {
        // Converter erros PHP em exceções
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        // Handler global de exceções
        set_exception_handler([$this, 'handleException']);

        // Handler de erros fatais
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                $this->handleException(new ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));
            }
        });
    }

    public function handleException(Throwable $e): void
    {
        // Log estruturado do erro
        $this->logger->error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
            'uri' => $this->request->uri(),
            'method' => $this->request->method()
        ], 'error');

        // Se for uma requisição AJAX ou API, retornar JSON
        if ($this->request->isAjax() || str_starts_with($this->request->uri(), '/api')) {
            $this->response->exception($e);
        }

        // Caso contrário, renderizar página de erro amigável ou HTML simples
        $status = $e->getCode();
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        $this->response->html(
            sprintf(
                "<h1>Erro %d</h1><p>%s</p><hr><p><a href='/'>Voltar para o Início</a></p>",
                $status,
                htmlspecialchars($e->getMessage())
            ),
            (int)$status
        );
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function run(): void
    {
        $this->logger->info("Request: {$this->request->method()} {$this->request->uri()}", [
            'query' => $this->request->allQuery(),
            'input' => array_diff_key($this->request->allInput(), ['password' => '', 'api_key' => ''])
        ]);

        try {
            $routes = require $this->basePath . '/routes/web.php';

            if (is_callable($routes)) {
                $routes($this->router);
            }

            // Adicionar rotas do terminal
            $this->router->addTerminalRoutes();

            $this->router->dispatch(
                $this->request,
                $this->response,
                $this->view
            );
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }
}
