<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\UploadController;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/about', [HomeController::class, 'about']);
    $router->get('/health', [HomeController::class, 'health']);

    $router->get('/workspace', [WorkspaceController::class, 'index']);
    $router->post('/workspace/open', [WorkspaceController::class, 'open']);
    $router->post('/workspace/save', [WorkspaceController::class, 'saveFile']);
    $router->post('/workspace/git/stage', [WorkspaceController::class, 'stageFile']);
    $router->post('/workspace/git/commit', [WorkspaceController::class, 'commit']);
    $router->post('/workspace/git/pull', [WorkspaceController::class, 'pull']);
    $router->post('/workspace/git/push', [WorkspaceController::class, 'push']);
    $router->get('/workspace/git/branches', [WorkspaceController::class, 'listBranches']);
    $router->post('/workspace/git/switch', [WorkspaceController::class, 'switchBranch']);
    $router->post('/workspace/apply-ai', [WorkspaceController::class, 'applyAiSuggestion']);
    $router->post('/workspace/undo-ai', [WorkspaceController::class, 'undoAiSuggestion']);
    $router->post('/attachments/upload', [UploadController::class, 'upload']);
    $router->post('/attachments/delete', [UploadController::class, 'delete']);

    $router->post('/chat/send', [ChatController::class, 'send']);
    $router->post('/chat/clear', [ChatController::class, 'clear']);
};