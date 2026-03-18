<?php

declare(strict_types=1);

namespace App\Core;

class Middleware
{
    public static function auth(Request $request, Response $response, Logger $logger, callable $next): mixed
    {
        if (!Application::isAuthenticated()) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Não autenticado.'], 401);
            } else {
                header('Location: ' . Application::config('app.url') . '/login');
                exit;
            }
        }
        return $next($request, $response, $logger);
    }
}
