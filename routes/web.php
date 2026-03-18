<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\TerminalController;

return function (Router $router): void {
    // Públicas
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/about', [HomeController::class, 'about']);
    $router->get('/health', [HomeController::class, 'health']);

    // Workspace
    $router->get('/workspace', [WorkspaceController::class, 'index']);
    $router->get('/workspace/git/branches', [WorkspaceController::class, 'listBranches']);

    $router->post('/workspace/open', [WorkspaceController::class, 'open']);
    $router->post('/workspace/save', [WorkspaceController::class, 'saveFile']);
    $router->post('/workspace/git/stage', [WorkspaceController::class, 'stageFile']);
    $router->post('/workspace/git/commit', [WorkspaceController::class, 'commit']);
    $router->post('/workspace/git/pull', [WorkspaceController::class, 'pull']);
    $router->post('/workspace/git/push', [WorkspaceController::class, 'push']);
    $router->post('/workspace/git/switch', [WorkspaceController::class, 'switchBranch']);
    $router->post('/workspace/apply-ai', [WorkspaceController::class, 'applyAiSuggestion']);
    $router->post('/workspace/undo-ai', [WorkspaceController::class, 'undoAiSuggestion']);

    // Uploads
    $router->post('/attachments/upload', [UploadController::class, 'upload']);
    $router->post('/attachments/delete', [UploadController::class, 'delete']);

    // Chat
    $router->post('/chat/send', [ChatController::class, 'send']);
    $router->post('/chat/clear', [ChatController::class, 'clear']);

    // Queue
    $router->post('/queue/start-worker', [QueueController::class, 'startWorker']);
    $router->get('/queue/jobs', [QueueController::class, 'getJobs']);
    $router->post('/queue/clear-completed', [QueueController::class, 'clearCompletedJobs']);

    // Terminal
    $router->post('/workspace/terminal/execute', [TerminalController::class, 'execute']);

    // Auth
    $router->get('/login', [AuthController::class, 'showLoginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/logout', [AuthController::class, 'logout']);
};