<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    public function __construct(
        protected string $basePath
    ) {
    }

    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($view, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, array_merge($data, [
            'content' => $content,
        ]));
    }

    protected function renderFile(string $view, array $data = []): string
    {
        $viewPath = $this->basePath . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    }
}