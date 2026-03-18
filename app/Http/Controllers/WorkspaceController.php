<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\AiResponseStore;
use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Git\GitService;
use App\Workspace\WorkspaceManager;
use App\Utils\DiffApplier;
use App\Core\Logger;
use RuntimeException;
use App\Chat\ChatHistoryManager;

class WorkspaceController extends Controller
{
    protected WorkspaceManager $workspace;
    protected GitService $git;
    protected DiffApplier $diffApplier;
    protected string $baseUrl;
    protected ChatHistoryManager $chatHistoryManager;
    protected AiResponseStore $aiResponseStore;

    public function __construct(View $view, Response $response, Logger $logger)
    {
        parent::__construct($view, $response, $logger);
        $this->baseUrl = rtrim(Application::config('app.url') ?? '', '/');
        $basePath = dirname(__DIR__, 3);

        $this->workspace = new WorkspaceManager($basePath);
        $this->git = new GitService();
        $this->diffApplier = new DiffApplier();
        $this->chatHistoryManager = new ChatHistoryManager($basePath);
        $this->aiResponseStore = new AiResponseStore($basePath);
    }

    public function index(Request $request): void
    {
        $items = [];
        $error = null;
        $rootPath = $this->workspace->getRootPath();
        $currentPath = (string) $request->query('path', '');
        $filePath = (string) $request->query('file', '');
        $fileContent = null;
        $fileError = null;
        $gitDiff = null;
        $gitDiffError = null;

        $gitData = [
            'enabled' => false,
            'branch' => null,
            'status' => [],
            'commits' => [],
            'error' => null,
        ];

        try {
            if ($this->workspace->hasWorkspace()) {
                $items = $this->workspace->listFiles($currentPath);
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($filePath !== '') {
            try {
                $fileContent = $this->workspace->readFile($filePath);
            } catch (RuntimeException $e) {
                $fileError = $e->getMessage();
            }
        }

        if ($rootPath) {
            try {
                if ($this->git->isRepository($rootPath)) {
                    $gitData['enabled'] = true;
                    $gitData['branch'] = $this->git->getCurrentBranch($rootPath);
                    $gitData['status'] = $this->git->getStatus($rootPath);
                    $gitData['commits'] = $this->git->getRecentCommits($rootPath);

                    if ($filePath !== '') {
                        $gitDiff = $this->git->getDiff($rootPath, $filePath);
                    }
                }
            } catch (RuntimeException $e) {
                $gitData['error'] = $e->getMessage();
            }
        }

        $chatHistory = $this->chatHistoryManager->loadHistory();
        $attachments = $_SESSION['uploaded_attachments'] ?? [];
        
        $this->render('workspace', [
            'title' => 'Workspace IDE',
            'rootPath' => $rootPath,
            'items' => $items,
            'error' => $error,
            'currentPath' => $currentPath,
            'filePath' => $filePath,
            'fileContent' => $fileContent,
            'fileError' => $fileError,
            'baseUrl' => $this->baseUrl,
            'gitData' => $gitData,
            'gitDiff' => $gitDiff,
            'gitDiffError' => $gitDiffError,
            'chatHistory' => $chatHistory,
            'attachments' => $attachments,
        ], 200, 'layouts.ide');
    }

    public function applyAiSuggestion(Request $request): void
    {
        $filePath = (string) $request->input('file_path');
        [$lastResponse, $lastResponseFile] = $this->resolveLastAiResponse();

        try {
            if ($filePath === '') {
                throw new RuntimeException('Nenhum arquivo foi informado para aplicação.');
            }

            if ($lastResponse === '') {
                throw new RuntimeException('Nenhuma resposta recente da IA encontrada.');
            }

            if ($lastResponseFile !== '' && $lastResponseFile !== $filePath) {
                throw new RuntimeException('A última resposta da IA pertence a outro arquivo.');
            }

            $currentContent = $this->workspace->readFile($filePath);

            $diff = $this->extractDiffBlock($lastResponse);
            if ($diff !== null) {
                try {
                    $newContent = $this->diffApplier->applyPatch($currentContent, $diff);
                    $this->workspace->createBackup($filePath);
                    $this->workspace->writeFile($filePath, $newContent, true);
                    $_SESSION["last_applied_ai_file"] = $filePath;
                    $this->success("Patch de diff aplicado com sucesso. Backup criado.", [
                        "path" => $filePath,
                        "mode" => "diff",
                    ]);
                    return;
                } catch (RuntimeException $e) {
                    $this->logger->error('Falha ao aplicar patch de diff: ' . $e->getMessage(), ['file' => $filePath, 'diff' => $diff], 'ai');
                }
            }

            $replaceInstruction = $this->extractReplaceInstruction($lastResponse);

            if ($replaceInstruction !== null) {
                $find = $replaceInstruction["find"];
                $replace = $replaceInstruction["replace"];

                if ($find === "") {
                    throw new RuntimeException("A instrução da IA não contém um trecho LOCALIZAR válido.");
                }

                if (!str_contains($currentContent, $find)) {
                    throw new RuntimeException("O trecho informado pela IA não foi encontrado no arquivo atual.");
                }

                $newContent = preg_replace("/".preg_quote($find, "/")."/", $replace, $currentContent, 1);

                if ($newContent === null) {
                    throw new RuntimeException("Falha ao aplicar substituição parcial.");
                }

                $this->workspace->createBackup($filePath);
                $this->workspace->writeFile($filePath, $newContent, true);

                $_SESSION["last_applied_ai_file"] = $filePath;

                $this->success("Substituição parcial aplicada com sucesso. Backup criado.", [
                    "path" => $filePath,
                    "mode" => "replace",
                ]);
                return;
            }

            $code = $this->extractCodeBlock($lastResponse);

            if ($code === "") {
                throw new RuntimeException("Nenhuma instrução parcial, diff ou bloco de código válido foi encontrado na última resposta da IA.");
            }

            $this->workspace->createBackup($filePath);
            $this->workspace->writeFile($filePath, $code, true);

            $_SESSION['last_applied_ai_file'] = $filePath;

            $this->success('Arquivo completo aplicado com sucesso. Backup criado.', [
                'path' => $filePath,
                'mode' => 'full',
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function open(Request $request): void
    {
        $path = (string) $request->input('root_path');

        try {
            $this->workspace->setRootPath($path);
            header('Location: ' . $this->baseUrl . '/workspace');
            exit;
        } catch (RuntimeException $e) {
            $this->response->html(
                '<p>Erro: ' . htmlspecialchars($e->getMessage()) . '</p><p><a href="' . $this->baseUrl . '/workspace">Voltar</a></p>',
                400
            );
        }
    }

    public function saveFile(Request $request): void
    {
        $path = (string) $request->input('path');
        $content = (string) $request->input('content');

        $this->workspace->writeFile($path, $content);

        $this->success('Arquivo salvo com sucesso.', [
            'path' => $path,
        ]);
    }

    public function stageFile(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $path = (string) $request->input('path');

        if (!$rootPath || !$this->git->isRepository($rootPath)) {
            throw new RuntimeException('Workspace atual não é um repositório Git.');
        }

        $this->git->stageFile($rootPath, $path);

        $this->success('Arquivo adicionado ao stage.', [
            'path' => $path,
        ]);
    }

    public function commit(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $message = (string) $request->input('message');

        if (!$rootPath || !$this->git->isRepository($rootPath)) {
            throw new RuntimeException('Workspace atual não é um repositório Git.');
        }

        $output = $this->git->commit($rootPath, $message);

        $this->success('Commit realizado com sucesso.', [
            'output' => $output,
        ]);
    }

    public function pull(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $remote = (string) $request->input('remote', 'origin');
        $branch = (string) $request->input('branch');

        if (!$rootPath || !$this->git->isRepository($rootPath)) {
            throw new RuntimeException('Workspace atual não é um repositório Git.');
        }

        $output = $this->git->pull($rootPath, $remote, $branch ?: null);

        $this->success('Pull realizado com sucesso.', [
            'output' => $output,
        ]);
    }

    public function push(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $remote = (string) $request->input('remote', 'origin');
        $branch = (string) $request->input('branch');

        if (!$rootPath || !$this->git->isRepository($rootPath)) {
            throw new RuntimeException('Workspace atual não é um repositório Git.');
        }

        $output = $this->git->push($rootPath, $remote, $branch ?: null);

        $this->success('Push realizado com sucesso.', [
            'output' => $output,
        ]);
    }

    public function listBranches(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();

        if (!$rootPath || !$this->git->isRepository($rootPath)) {
            throw new RuntimeException('Workspace atual não é um repositório Git.');
        }

        $branches = $this->git->listBranches($rootPath);
        $current = $this->git->getCurrentBranch($rootPath);

        $this->success('Branches listadas com sucesso.', [
            'branches' => $branches,
            'current' => $current,
        ]);
    }

    public function switchBranch(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $branch = (string) $request->input('branch');
        $create = (bool) $request->input('create', false);

        if (!$rootPath || !$this->git->isRepository($rootPath)) {
            throw new RuntimeException('Workspace atual não é um repositório Git.');
        }

        if ($branch === '') {
            throw new RuntimeException('Nome da branch não informado.');
        }

        $output = $this->git->switchBranch($rootPath, $branch, $create);

        $this->success('Branch alterada com sucesso.', [
            'output' => $output,
            'current' => $branch,
        ]);
    }

    protected function resolveLastAiResponse(): array
    {
        $lastResponse = (string) ($_SESSION['last_ai_response'] ?? '');
        $lastResponseFile = (string) ($_SESSION['last_ai_file_path'] ?? '');

        if ($lastResponse === '') {
            $stored = $this->aiResponseStore->load();
            $lastResponse = (string) ($stored['response'] ?? '');
            $lastResponseFile = (string) ($stored['file_path'] ?? '');

            if ($lastResponse !== '') {
                $_SESSION['last_ai_response'] = $lastResponse;
                if ($lastResponseFile !== '') {
                    $_SESSION['last_ai_file_path'] = $lastResponseFile;
                }
            }
        }

        return [$lastResponse, $lastResponseFile];
    }

    protected function extractDiffBlock(string $text): ?string
    {
        $pattern = '/```diff\s*(.*?)```/s';
        if (preg_match($pattern, $text, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }
        return null;
    }

    protected function extractReplaceInstruction(string $text): ?array
    {
        $pattern = '/LOCALIZAR:\s*(.*?)\s*SUBSTITUIR POR:\s*(.*)/s';
        if (!preg_match($pattern, $text, $matches)) {
            return null;
        }
        $find = trim((string) ($matches[1] ?? ''));
        $replace = trim((string) ($matches[2] ?? ''));
        $find = preg_replace('/^```[a-zA-Z]*\s*/', '', $find);
        $find = preg_replace('/```$/', '', $find);
        $replace = preg_replace('/^```[a-zA-Z]*\s*/', '', $replace);
        $replace = preg_replace('/```$/', '', $replace);
        $find = trim($find);
        $replace = trim($replace);
        if ($find === '' && $replace === '') {
            return null;
        }
        return [
            'find' => $find,
            'replace' => $replace,
        ];
    }

    protected function extractCodeBlock(string $text): string
    {
        $pattern = '/```[a-zA-Z0-9]*\s*(.*?)```/s';
        if (preg_match($pattern, $text, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }
        return '';
    }
}
