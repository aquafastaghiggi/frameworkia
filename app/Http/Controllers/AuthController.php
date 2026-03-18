<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Logger;
use App\Core\Application;

class AuthController extends Controller
{
    public function __construct(View $view, Response $response, Logger $logger)
    {
        parent::__construct($view, $response, $logger);
    }

    public function showLoginForm(): void
    {
        $this->render('login', ['title' => 'Login'], 200, 'layouts.auth');
    }

    public function login(Request $request): void
    {
        $username = (string) $request->input('username');
        $password = (string) $request->input('password');

        // Credenciais simples para demonstração
        if ($username === 'admin' && $password === 'admin') {
            $_SESSION['authenticated'] = true;
            header('Location: ' . Application::config('app.url') . '/workspace');
            exit;
        } else {
            $this->render('login', ['title' => 'Login', 'error' => 'Credenciais inválidas.'], 401, 'layouts.auth');
        }
    }

    public function logout(): void
    {
        unset($_SESSION['authenticated']);
        session_destroy();
        header('Location: ' . Application::config('app.url') . '/');
        exit;
    }
}
