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

    protected function text(string $content, int $status = 200): void
    {
        $this->response->text($content, $status);
    }
}