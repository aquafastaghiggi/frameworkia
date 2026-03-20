<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Git\GitService;
use App\Workspace\WorkspaceManager;
use App\Code\CodeModifier;
use RuntimeException;

class WorkspaceController extends Controller
{
    protected WorkspaceManager $workspace;
    protected GitService $git;
    protected CodeModifier $modifier;
    protected string $baseUrl = '/framework/public';

protected function extractReplaceInstruction(string $text): ?array
{
    $pattern = '/LOCALIZAR:\s*(.*?)\s*SUBSTITUIR POR:\s*(.*)/s';

    if (!preg_match($pattern, $text, $matches)) {
        return null;
    }

    $find = trim((string) ($matches[1] ?? ''));
    $replace = trim((string) ($matches[2] ?? ''));

    // 🔥 remove blocos ```php ``` se existirem
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

public function applyAiSuggestion(Request $request): void
{
    $filePath = (string) $request->input('file_path');
    $lastResponse = (string) ($_SESSION['last_ai_response'] ?? '');
    $lastResponseFile = (string) ($_SESSION['last_ai_file_path'] ?? '');

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

        $replaceInstruction = $this->extractReplaceInstruction($lastResponse);

        if ($replaceInstruction !== null) {
            $find = $replaceInstruction['find'];
            $replace = $replaceInstruction['replace'];

            if ($find === '') {
                throw new RuntimeException('A instrução da IA não contém um trecho LOCALIZAR válido.');
            }

            if (!str_contains($currentContent, $find)) {
                throw new RuntimeException('O trecho informado pela IA não foi encontrado no arquivo atual.');
            }

            $newContent = preg_replace('/' . preg_quote($find, '/') . '/', $replace, $currentContent, 1);

            if ($newContent === null) {
                throw new RuntimeException('Falha ao aplicar substituição parcial.');
            }

            $this->workspace->createBackup($filePath);
            $this->workspace->writeFile($filePath, $newContent);

            $_SESSION['last_applied_ai_file'] = $filePath;

            $this->json([
                'success' => true,
                'message' => 'Substituição parcial aplicada com sucesso. Backup criado.',
                'path' => $filePath,
                'mode' => 'replace',
            ]);
            return;
        }

        $code = $this->extractCodeBlock($lastResponse);

        if ($code === '') {
            throw new RuntimeException('Nenhuma instrução parcial nem bloco de código válido foi encontrado na última resposta da IA.');
        }

        $this->workspace->createBackup($filePath);
        $this->workspace->writeFile($filePath, $code);

        $_SESSION['last_applied_ai_file'] = $filePath;

        $this->json([
            'success' => true,
            'message' => 'Arquivo completo aplicado com sucesso. Backup criado.',
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

    public function undoAiSuggestion(Request $request): void
    {
        $caminhoArquivo = (string) $request->input('arquivo');

        try {
            if ($caminhoArquivo === '') {
                throw new RuntimeException('Nenhum arquivo foi informado.');
            }

            $this->workspace->restoreBackup($caminhoArquivo);
            $this->workspace->deleteBackup($caminhoArquivo);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Alteração desfeita com sucesso.',
                'arquivo' => $caminhoArquivo,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    public function previewAiSuggestion(Request $request): void
    {
        $caminhoArquivo = (string) $request->input('arquivo');
        $conteudoNovo = (string) $request->input('conteudo_novo');

        try {
            if ($caminhoArquivo === '') {
                throw new RuntimeException('Nenhum arquivo foi informado.');
            }

            if ($conteudoNovo === '') {
                throw new RuntimeException('Nenhum conteúdo novo foi fornecido.');
            }

            $conteudoOriginal = $this->workspace->readFile($caminhoArquivo);
            $preview = $this->modifier->gerarPreview($caminhoArquivo, $conteudoOriginal, $conteudoNovo);

            $this->json([
                'sucesso' => true,
                'preview' => $preview,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    public function confirmarAiSuggestion(Request $request): void
    {
        $caminhoArquivo = (string) $request->input('arquivo');
        $conteudoNovo = (string) $request->input('conteudo_novo');

        try {
            if ($caminhoArquivo === '') {
                throw new RuntimeException('Nenhum arquivo foi informado.');
            }

            if ($conteudoNovo === '') {
                throw new RuntimeException('Nenhum conteúdo novo foi fornecido.');
            }

            $conteudoOriginal = $this->workspace->readFile($caminhoArquivo);

            $resultado = $this->modifier->aplicarSubstituicaoSegura(
                $caminhoArquivo,
                $conteudoOriginal,
                $conteudoNovo,
                fn($arquivo, $conteudo) => $this->workspace->createBackup($arquivo),
                fn($arquivo, $conteudo) => $this->workspace->writeFile($arquivo, $conteudo)
            );

            $_SESSION['last_applied_ai_file'] = $caminhoArquivo;

            $this->json([
                'sucesso' => true,
                'mensagem' => $resultado['mensagem'],
                'arquivo' => $caminhoArquivo,
                'backup' => $resultado['backup'],
                'avisos' => $resultado['avisos'],
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    public function relatorioMudancas(Request $request): void
    {
        $caminhoArquivo = (string) $request->input('arquivo');
        $conteudoNovo = (string) $request->input('conteudo_novo');

        try {
            if ($caminhoArquivo === '') {
                throw new RuntimeException('Nenhum arquivo foi informado.');
            }

            if ($conteudoNovo === '') {
                throw new RuntimeException('Nenhum conteúdo novo foi fornecido.');
            }

            $conteudoOriginal = $this->workspace->readFile($caminhoArquivo);
            $relatorio = $this->modifier->gerarRelatorio($caminhoArquivo, $conteudoOriginal, $conteudoNovo);

            $this->json([
                'sucesso' => true,
                'relatorio' => $relatorio,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    public function __construct(View $view, Response $response)
    {
        parent::__construct($view, $response);
        $basePath = dirname(__DIR__, 3);

        $this->workspace = new WorkspaceManager($basePath);
        $this->git = new GitService();
        $this->modifier = new CodeModifier();
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
        //adicionei aqui passo 7
        $chatHistory = $_SESSION['chat_history'] ?? [];
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

        try {
            $this->workspace->writeFile($path, $content);

            $this->json([
                'success' => true,
                'message' => 'Arquivo salvo com sucesso.',
                'path' => $path,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function stageFile(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $path = (string) $request->input('path');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $this->git->stageFile($rootPath, $path);

            $this->json([
                'success' => true,
                'message' => 'Arquivo adicionado ao stage.',
                'path' => $path,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function commit(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $message = (string) $request->input('message');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->commit($rootPath, $message);

            $this->json([
                'success' => true,
                'message' => 'Commit realizado com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function unstageFile(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $path = (string) $request->input('path');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $this->git->unstageFile($rootPath, $path);

            $this->json([
                'success' => true,
                'message' => 'Arquivo removido do stage.',
                'path' => $path,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function discardChanges(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $path = (string) $request->input('path');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $this->git->discardChanges($rootPath, $path);

            $this->json([
                'success' => true,
                'message' => 'Alterações descartadas.',
                'path' => $path,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function push(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $remote = (string) $request->input('remote', null);
        $branch = (string) $request->input('branch', null);

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->push($rootPath, $remote ?: null, $branch ?: null);

            $this->json([
                'success' => true,
                'message' => 'Push realizado com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function pull(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $remote = (string) $request->input('remote', null);
        $branch = (string) $request->input('branch', null);

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->pull($rootPath, $remote ?: null, $branch ?: null);

            $this->json([
                'success' => true,
                'message' => 'Pull realizado com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function fetch(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $remote = (string) $request->input('remote', null);

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->fetch($rootPath, $remote ?: null);

            $this->json([
                'success' => true,
                'message' => 'Fetch realizado com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getBranches(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $branches = $this->git->getBranches($rootPath);

            $this->json([
                'success' => true,
                'branches' => $branches,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function createBranch(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $branchName = (string) $request->input('name');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->createBranch($rootPath, $branchName);

            $this->json([
                'success' => true,
                'message' => 'Branch criada com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deleteBranch(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $branchName = (string) $request->input('name');
        $force = (bool) $request->input('force', false);

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->deleteBranch($rootPath, $branchName, $force);

            $this->json([
                'success' => true,
                'message' => 'Branch deletada com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function switchBranch(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $branchName = (string) $request->input('name');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->switchBranch($rootPath, $branchName);

            $this->json([
                'success' => true,
                'message' => 'Branch alterada com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getCommitHistory(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $limit = (int) $request->input('limit', 20);

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $commits = $this->git->getCommitHistory($rootPath, $limit);

            $this->json([
                'success' => true,
                'commits' => $commits,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getCommitDetails(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $hash = (string) $request->input('hash');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $details = $this->git->getCommitDetails($rootPath, $hash);
            $files = $this->git->getFilesInCommit($rootPath, $hash);

            $this->json([
                'success' => true,
                'details' => $details,
                'files' => $files,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getRemotes(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $remotes = $this->git->getRemotes($rootPath);

            $this->json([
                'success' => true,
                'remotes' => $remotes,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function addRemote(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $name = (string) $request->input('name');
        $url = (string) $request->input('url');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->addRemote($rootPath, $name, $url);

            $this->json([
                'success' => true,
                'message' => 'Remote adicionado com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function removeRemote(Request $request): void
    {
        $rootPath = $this->workspace->getRootPath();
        $name = (string) $request->input('name');

        try {
            if (!$rootPath || !$this->git->isRepository($rootPath)) {
                throw new RuntimeException('Workspace atual não é um repositório Git.');
            }

            $output = $this->git->removeRemote($rootPath, $name);

            $this->json([
                'success' => true,
                'message' => 'Remote removido com sucesso.',
                'output' => $output,
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}