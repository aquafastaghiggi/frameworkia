<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\ChatService;
use App\AI\MockAIProvider;
use App\AI\OpenAIProvider;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Documents\DocumentManager;
use App\Git\GitService;
use App\Workspace\WorkspaceManager;
use App\Workspace\FileTree;
use App\Chat\ChatHistoryManager;

class ChatController extends Controller
{
    protected ChatService $chatService;
    protected WorkspaceManager $workspace;
    protected GitService $git;
    protected DocumentManager $documents;
    protected FileTree $fileTree;
    protected ChatHistoryManager $chatHistoryManager;

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
        $this->chatService->setLogger(new Logger($basePath));
        $this->workspace = new WorkspaceManager($basePath);
        $this->git = new GitService();
        $this->documents = new DocumentManager();
        $this->fileTree = new FileTree();
        $this->chatHistoryManager = new ChatHistoryManager($basePath);
    }

    public function send(Request $request): void
{
    try {
        $prompt = (string) $request->input('prompt');
        $filePath = (string) $request->input('file_path');
        $currentPath = (string) $request->input('current_path');
        $attachmentPaths = $request->input('attachment_paths', []);
        if (!is_array($attachmentPaths)) {
            $attachmentPaths = $attachmentPaths !== '' ? [(string)$attachmentPaths] : [];
        }
        
        // Retrocompatibilidade com attachment_path único
        $singleAttachment = (string) $request->input('attachment_path');
        if ($singleAttachment !== '' && !in_array($singleAttachment, $attachmentPaths, true)) {
            $attachmentPaths[] = $singleAttachment;
        }

        $role = (string) $request->input('role', 'dev');

        $rootPath = $this->workspace->getRootPath() ?? '';
        $fileContent = '';
        $gitDiff = '';
        $projectStructure = '';
        $attachments = [];

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

        $basePath = dirname(__DIR__, 3);
        foreach ($attachmentPaths as $path) {
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', (string)$path), '/');
            try {
                $document = $this->documents->read($fullPath);
                $attachments[] = [
                    'path' => $path,
                    'type' => (string) ($document['type'] ?? 'unknown'),
                    'summary' => (string) ($document['summary'] ?? ''),
                    'content' => (string) ($document['full_text'] ?? ''),
                ];
            } catch (\Throwable $e) {
                $attachments[] = [
                    'path' => $path,
                    'type' => 'error',
                    'summary' => 'Falha ao ler o anexo: ' . $e->getMessage(),
                    'content' => '',
                ];
            }
        }

        $context = [
            'workspace' => $rootPath,
            'file_path' => $filePath,
            'current_path' => $currentPath,
            'file_content' => $fileContent,
            'git_diff' => $gitDiff,
            'project_structure' => $projectStructure,
            'attachments' => $attachments,
            'role' => $role,
        ];

        $result = $this->chatService->send($prompt, $context);

        if (!$result['success']) {
            throw new \RuntimeException($result['message'] ?? 'Erro ao processar chat com IA.');
        }

        $chatHistory = $this->chatHistoryManager->loadHistory();

        $this->chatHistoryManager->addMessage("user", $prompt);

        $this->chatHistoryManager->addMessage("assistant", $result["response"]);

        $_SESSION['last_ai_response'] = $result['response'];
        $_SESSION['last_ai_file_path'] = $filePath;

        $this->success("Chat processado com sucesso.", [
            "response" => $result["response"],
            "history" => $this->chatHistoryManager->loadHistory(),
        ]);
    } catch (\Throwable $e) {
        throw $e;
    }
}

    public function clear(Request $request): void
    {
        $this->chatHistoryManager->clearHistory();

        $this->success("Histórico limpo com sucesso.", [
            "history" => [],
        ]);
    }
}