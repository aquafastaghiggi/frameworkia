<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(
        protected View $view,
        protected Response $response
    ) {
    }

    protected function render(string $view, array $data = [], int $status = 200, ?string $layout = null): void
    {
        $content = $this->view->render($view, $data, $layout);
        $this->response->html($content, $status);
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->response->json($data, $status);
    }

    protected function success(string $message = 'Operação realizada com sucesso', array $data = [], int $status = 200): void
    {
        $this->response->success($message, $data, $status);
    }

    protected function error(string $message = 'Ocorreu um erro inesperado', int $status = 400, array $details = []): void
    {
        $this->response->error($message, $status, $details);
    }

    protected function text(string $content, int $status = 200): void
    {
        $this->response->text($content, $status);
    }
}