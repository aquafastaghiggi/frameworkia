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
    $router->post('/workspace/git/unstage', [WorkspaceController::class, 'unstageFile']);
    $router->post('/workspace/git/discard', [WorkspaceController::class, 'discardChanges']);
    $router->post('/workspace/git/commit', [WorkspaceController::class, 'commit']);
    $router->post('/workspace/git/push', [WorkspaceController::class, 'push']);
    $router->post('/workspace/git/pull', [WorkspaceController::class, 'pull']);
    $router->post('/workspace/git/fetch', [WorkspaceController::class, 'fetch']);
    $router->get('/workspace/git/branches', [WorkspaceController::class, 'getBranches']);
    $router->post('/workspace/git/branch/create', [WorkspaceController::class, 'createBranch']);
    $router->post('/workspace/git/branch/delete', [WorkspaceController::class, 'deleteBranch']);
    $router->post('/workspace/git/branch/switch', [WorkspaceController::class, 'switchBranch']);
    $router->get('/workspace/git/history', [WorkspaceController::class, 'getCommitHistory']);
    $router->get('/workspace/git/commit/:hash', [WorkspaceController::class, 'getCommitDetails']);
    $router->get('/workspace/git/remotes', [WorkspaceController::class, 'getRemotes']);
    $router->post('/workspace/git/remote/add', [WorkspaceController::class, 'addRemote']);
    $router->post('/workspace/git/remote/remove', [WorkspaceController::class, 'removeRemote']);
    $router->post('/workspace/apply-ai', [WorkspaceController::class, 'applyAiSuggestion']);
    $router->post('/workspace/undo-ai', [WorkspaceController::class, 'undoAiSuggestion']);
    $router->post('/workspace/preview-ai', [WorkspaceController::class, 'previewAiSuggestion']);
    $router->post('/workspace/confirm-ai', [WorkspaceController::class, 'confirmarAiSuggestion']);
    $router->post('/workspace/relatorio-ai', [WorkspaceController::class, 'relatorioMudancas']);
    $router->post('/attachments/upload', [UploadController::class, 'upload']);
    $router->post('/attachments/delete', [UploadController::class, 'delete']);
    $router->get('/attachments/content', [UploadController::class, 'lerConteudo']);
    $router->get('/attachments/metadata', [UploadController::class, 'obterMetadados']);
    $router->post('/attachments/search', [UploadController::class, 'buscar']);
    $router->post('/attachments/indexar', [UploadController::class, 'indexar']);
    $router->get('/attachments/buscar-indice', [UploadController::class, 'buscarNoIndice']);
    $router->get('/attachments/estatisticas', [UploadController::class, 'obterEstatisticas']);
    $router->get('/attachments/lista-documentos', [UploadController::class, 'listarDocumentos']);
    $router->get('/attachments/tipos-suportados', [UploadController::class, 'tiposSuportados']);
    $router->get('/attachments/analisar', [UploadController::class, 'analisar']);
    $router->get('/attachments/entidades', [UploadController::class, 'extrairEntidades']);
    $router->post('/attachments/detectar-tipos', [UploadController::class, 'detectarTipos']);
    $router->post('/attachments/detectar-padroes', [UploadController::class, 'detectarPadroes']);
    $router->get('/attachments/detectar-idioma', [UploadController::class, 'detectarIdioma']);
    $router->get('/attachments/resumo-automatico', [UploadController::class, 'gerarResumo']);

    $router->post('/chat/send', [ChatController::class, 'send']);
    $router->post('/chat/clear', [ChatController::class, 'clear']);

    // FASE 11: Multi-Context AI
    $router->post('/chat/multi-context', [ChatController::class, 'sendMultiContext']);
    $router->get('/chat/historico-conversas', [ChatController::class, 'obterHistoricoConversas']);
    $router->post('/chat/carregar-conversa', [ChatController::class, 'carregarConversa']);
    $router->post('/chat/iniciar-conversa', [ChatController::class, 'iniciarConversa']);
    $router->post('/chat/limpar-conversa', [ChatController::class, 'limparConversa']);
    $router->post('/chat/exportar-conversa', [ChatController::class, 'exportarConversa']);
};