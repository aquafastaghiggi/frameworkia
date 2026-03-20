<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;

class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->render('home', [
            'title' => 'Mini Framework IA',
            'message' => 'Etapa 2 concluída parcialmente com MVC básico.',
        ]);
    }

    public function about(Request $request): void
    {
        $this->render('about', [
            'title' => 'Sobre',
            'description' => 'Projeto base do mini framework com arquitetura inicial profissional.',
        ]);
    }

    public function health(Request $request): void
    {
        $this->json([
            'status' => 'ok',
            'app' => 'mini-ai-framework',
            'php' => PHP_VERSION,
            'stage' => 2,
        ]);
    }
}