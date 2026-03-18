<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\ChatService;
use App\AI\MockAIProvider;
use App\AI\OpenAIProvider;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Documents\DocumentManager;
use App\Git\GitService;
use App\Workspace\WorkspaceManager;
use App\Workspace\FileTree;

class ChatController extends Controller
{
    protected ChatService $chatService;
    protected WorkspaceManager $workspace;
    protected GitService $git;
    protected DocumentManager $documents;
    protected FileTree $fileTree;

    public function __construct(View $view, Response $response)
    {
        parent::__construct($view, $response);

        $basePath = dirname(__DIR__, 3);

        $aiConfig = require $basePath . '/config/ai.php';
        $providerName = $aiConfig['provider'] ?? 'mock';

        if ($providerName === 'openai') {
            $provider = new OpenAIProvider($aiConfig['openai'] ?? []);
        } else {
            $provider = new MockAIProvider();
        }

        $this->chatService = new ChatService($provider);
        $this->workspace = new WorkspaceManager($basePath);
        $this->git = new GitService();
        $this->documents = new DocumentManager();
        $this->fileTree = new FileTree();
    }

    public function send(Request $request): void
{
    try {
        $prompt = (string) $request->input('prompt');
        $filePath = (string) $request->input('file_path');
        $currentPath = (string) $request->input('current_path');
        $attachmentPath = (string) $request->input('attachment_path');
        $role = (string) $request->input('role', 'dev');

        $rootPath = $this->workspace->getRootPath() ?? '';
        $fileContent = '';
        $gitDiff = '';
        $projectStructure = '';
        $attachmentContent = '';
        $attachmentSummary = '';
        $attachmentType = '';

        if ($filePath !== '') {
            try {
                $fileContent = $this->workspace->readFile($filePath);
            } catch (\Throwable $e) {
                $fileContent = '';
            }
        }

        if ($rootPath) {
            // Gerar estrutura do projeto
            $projectStructure = $this->fileTree->generate($rootPath);

            if ($filePath !== '') {
                try {
                    if ($this->git->isRepository($rootPath)) {
                        $gitDiff = $this->git->getDiff($rootPath, $filePath);
                    }
                } catch (\Throwable $e) {
                    $gitDiff = '';
                }
            }
        }

        if ($attachmentPath !== '') {
            $basePath = dirname(__DIR__, 3);
            $fullAttachmentPath = $basePath . '/' . ltrim(str_replace('\\', '/', $attachmentPath), '/');

            try {
                $document = $this->documents->read($fullAttachmentPath);
                $attachmentType = (string) ($document['type'] ?? '');
                $attachmentSummary = (string) ($document['summary'] ?? '');
                $attachmentContent = (string) ($document['full_text'] ?? '');
            } catch (\Throwable $e) {
                $attachmentType = 'unknown';
                $attachmentSummary = 'Falha ao ler o anexo: ' . $e->getMessage();
                $attachmentContent = '';
            }
        }

        $context = [
            'workspace' => $rootPath,
            'file_path' => $filePath,
            'current_path' => $currentPath,
            'file_content' => $fileContent,
            'git_diff' => $gitDiff,
            'project_structure' => $projectStructure,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
            'attachment_summary' => $attachmentSummary,
            'attachment_content' => $attachmentContent,
            'role' => $role,
        ];

        $result = $this->chatService->send($prompt, $context);

        if (!$result['success']) {
            throw new \RuntimeException($result['message'] ?? 'Erro ao processar chat com IA.');
        }

        if (!isset($_SESSION['chat_history']) || !is_array($_SESSION['chat_history'])) {
            $_SESSION['chat_history'] = [];
        }

        $_SESSION['chat_history'][] = [
            'role' => 'user',
            'content' => $prompt,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $_SESSION['chat_history'][] = [
            'role' => 'assistant',
            'content' => $result['response'],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $_SESSION['last_ai_response'] = $result['response'];
        $_SESSION['last_ai_file_path'] = $filePath;

        $this->success('Chat processado com sucesso.', [
            'response' => $result['response'],
            'history' => $_SESSION['chat_history'],
        ]);
}

    public function clear(Request $request): void
    {
        $_SESSION['chat_history'] = [];

        $this->success('Histórico limpo com sucesso.', [
            'history' => [],
        ]);
    }
}