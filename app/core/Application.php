<?php

declare(strict_types=1);

namespace App\Core;

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
        $routes = require $this->basePath . '/routes/web.php';

        if (is_callable($routes)) {
            $routes($this->router);
        }

        $this->router->dispatch(
            $this->request,
            $this->response,
            $this->view
        );
    }
}