<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QueueController;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get("/about", [HomeController::class, "about"]);

    // Rotas protegidas por autenticação
    $router->middleware("auth", [
        "GET" => [
            "/workspace" => [WorkspaceController::class, "index"],
            "/workspace/git/branches" => [WorkspaceController::class, "listBranches"],
        ],
        "POST" => [
            "/workspace/open" => [WorkspaceController::class, "open"],
            "/workspace/save" => [WorkspaceController::class, "saveFile"],
            "/workspace/git/stage" => [WorkspaceController::class, "stageFile"],
            "/workspace/git/commit" => [WorkspaceController::class, "commit"],
            "/workspace/git/pull" => [WorkspaceController::class, "pull"],
            "/workspace/git/push" => [WorkspaceController::class, "push"],
            "/workspace/git/switch" => [WorkspaceController::class, "switchBranch"],
            "/workspace/apply-ai" => [WorkspaceController::class, "applyAiSuggestion"],
            "/workspace/undo-ai" => [WorkspaceController::class, "undoAiSuggestion"],
            "/attachments/upload" => [UploadController::class, "upload"],
            "/attachments/delete" => [UploadController::class, "delete"],
            "/chat/send" => [ChatController::class, "send"],
            "/chat/clear" => [ChatController::class, "clear"],
            "/queue/start-worker" => [QueueController::class, "startWorker"],
            "/queue/jobs" => [QueueController::class, "getJobs"],
            "/queue/clear-completed" => [QueueController::class, "clearCompletedJobs"],
        ],
    ]);

    // Rotas do Terminal (também protegidas por autenticação)
    $router->middleware("auth", [
        "POST" => [
            "/workspace/terminal/execute" => [App\Http\Controllers\TerminalController::class, "execute"],
        ],
    ]);
    $router->get('/health', [HomeController::class, 'health']);



    // Rotas de Autenticação
    $router->get("/login", [AuthController::class, "showLoginForm"]);
    $router->post("/login", [AuthController::class, "login"]);
    $router->get("/logout", [AuthController::class, "logout"]);


};