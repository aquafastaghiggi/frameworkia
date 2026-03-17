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

    public function __construct(
        protected string $basePath
    ) {
        $this->router = new Router();
        $this->request = new Request();
        $this->response = new Response();
        $this->view = new View($this->basePath);

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
        // Log do erro
        error_log(sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

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
        try {
            $routes = require $this->basePath . '/routes/web.php';

            if (is_callable($routes)) {
                $routes($this->router);
            }

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
