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

class ChatController extends Controller
{
    protected ChatService $chatService;
    protected WorkspaceManager $workspace;
    protected GitService $git;
    protected DocumentManager $documents;

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
    }

    public function send(Request $request): void
{
    try {
        $prompt = (string) $request->input('prompt');
        $filePath = (string) $request->input('file_path');
        $currentPath = (string) $request->input('current_path');
        $attachmentPath = (string) $request->input('attachment_path');

        $rootPath = $this->workspace->getRootPath() ?? '';
        $fileContent = '';
        $gitDiff = '';
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

        if ($rootPath && $filePath !== '') {
            try {
                if ($this->git->isRepository($rootPath)) {
                    $gitDiff = $this->git->getDiff($rootPath, $filePath);
                }
            } catch (\Throwable $e) {
                $gitDiff = '';
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
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
            'attachment_summary' => $attachmentSummary,
            'attachment_content' => $attachmentContent,
        ];

        $result = $this->chatService->send($prompt, $context);

        if (!$result['success']) {
            $this->json($result, 400);
            return;
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

        $this->json([
            'success' => true,
            'message' => 'Chat processado com sucesso.',
            'response' => $result['response'],
            'history' => $_SESSION['chat_history'],
        ]);
    } catch (\Throwable $e) {
        $this->json([
            'success' => false,
            'message' => 'Erro interno no chat: ' . $e->getMessage(),
        ], 500);
    }
}

    public function clear(Request $request): void
    {
        $_SESSION['chat_history'] = [];

        $this->json([
            'success' => true,
            'message' => 'Histórico limpo com sucesso.',
            'history' => [],
        ]);
    }

    /**
     * FASE 11: Envia mensagem com múltiplas fontes de contexto
     * 
     * Aceita:
     * - prompt: string
     * - caminhos_código: array de caminhos para analisar
     * - caminhos_documentos: array de documentos para processar
     * - diretorio_raiz: raiz do workspace
     */
    public function sendMultiContext(Request $request): void
    {
        try {
            $prompt = (string) $request->input('prompt');
            $caminhosCódigo = (array) $request->input('caminhos_código', []);
            $caminhosDocumentos = (array) $request->input('caminhos_documentos', []);
            $diretorioRaiz = (string) $request->input('diretorio_raiz', '');

            if (empty($prompt)) {
                $this->json([
                    'sucesso' => false,
                    'mensagem' => 'O prompt não pode estar vazio.',
                ], 400);
                return;
            }

            // Construir contexto multi-arquivo
            $multiContextManager = new \App\AI\MultiContextManager();
            $contexto = $multiContextManager->construirContextoMulti([
                'caminhos_código' => $caminhosCódigo,
                'caminhos_documentos' => $caminhosDocumentos,
                'diretorio_raiz' => $diretorioRaiz,
                'incluir_git' => true,
                'incluir_estrutura' => true,
            ]);

            // Enriquecer prompt com contexto
            $promptEnriquecido = $multiContextManager->construirPromptMultiContexto($prompt, $contexto);

            // Enviar para IA
            $resultado = $this->chatService->send($promptEnriquecido, $contexto);

            if (!$resultado['success']) {
                $this->json([
                    'sucesso' => false,
                    'mensagem' => $resultado['message'] ?? 'Erro ao processar',
                ], 400);
                return;
            }

            // Salvar em memória de conversa
            if (!isset($_SESSION['conversation_memory'])) {
                $_SESSION['conversation_memory'] = new \App\AI\ConversationMemory();
            }

            $memoria = $_SESSION['conversation_memory'];
            $memoria->adicionarMensagem('user', $prompt, [
                'caminhos_código' => $caminhosCódigo,
                'caminhos_documentos' => $caminhosDocumentos,
            ]);
            $memoria->adicionarMensagem('assistant', $resultado['response']);

            // Manter histórico simples também
            if (!isset($_SESSION['chat_history'])) {
                $_SESSION['chat_history'] = [];
            }

            $_SESSION['chat_history'][] = [
                'role' => 'user',
                'content' => $prompt,
                'created_at' => date('Y-m-d H:i:s'),
                'contexto_multi' => true,
            ];

            $_SESSION['chat_history'][] = [
                'role' => 'assistant',
                'content' => $resultado['response'],
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Chat multi-contexto processado com sucesso.',
                'resposta' => $resultado['response'],
                'contexto' => [
                    'tokens_utilizados' => $contexto['metadata']['tokens_utilizados'],
                    'tokens_disponíveis' => $contexto['metadata']['tokens_disponiveis'],
                    'análises' => $contexto['análises'] ?? [],
                    'recomendações' => $contexto['recomendações'] ?? [],
                ],
                'histórico' => $_SESSION['chat_history'],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => 'Erro interno: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * FASE 11: Obtém histórico de conversas
     */
    public function obterHistoricoConversas(Request $request): void
    {
        try {
            if (!isset($_SESSION['conversation_memory'])) {
                $_SESSION['conversation_memory'] = new \App\AI\ConversationMemory();
            }

            $memoria = $_SESSION['conversation_memory'];
            $conversas = $memoria->listarConversas();

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Histórico de conversas obtido.',
                'conversas' => $conversas,
                'conversa_atual' => $memoria->obterConveraAtual(),
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao obter histórico: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * FASE 11: Carrega uma conversa específica
     */
    public function carregarConversa(Request $request): void
    {
        try {
            $id = (string) $request->input('id');

            if (empty($id)) {
                $this->json([
                    'sucesso' => false,
                    'mensagem' => 'ID da conversa não fornecido.',
                ], 400);
                return;
            }

            if (!isset($_SESSION['conversation_memory'])) {
                $_SESSION['conversation_memory'] = new \App\AI\ConversationMemory();
            }

            $memoria = $_SESSION['conversation_memory'];

            if (!$memoria->carregarConversa($id)) {
                $this->json([
                    'sucesso' => false,
                    'mensagem' => 'Conversa não encontrada.',
                ], 404);
                return;
            }

            $info = $memoria->obterInfoConveraAtual();
            $mensagens = $memoria->obterMensagens(10);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Conversa carregada com sucesso.',
                'conversa' => $info,
                'mensagens' => $mensagens,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar conversa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * FASE 11: Inicia nova conversa
     */
    public function iniciarConversa(Request $request): void
    {
        try {
            $titulo = (string) $request->input('titulo', 'Nova conversa');

            if (!isset($_SESSION['conversation_memory'])) {
                $_SESSION['conversation_memory'] = new \App\AI\ConversationMemory();
            }

            $memoria = $_SESSION['conversation_memory'];
            $memoria->iniciarConversa('', $titulo);
            $info = $memoria->obterInfoConveraAtual();

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Nova conversa iniciada.',
                'conversa' => $info,
                'id' => $memoria->obterConveraAtual(),
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao iniciar conversa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * FASE 11: Limpa uma conversa
     */
    public function limparConversa(Request $request): void
    {
        try {
            $id = (string) $request->input('id', '');
            $limparTodas = (bool) $request->input('limpar_todas', false);

            if (!isset($_SESSION['conversation_memory'])) {
                $_SESSION['conversation_memory'] = new \App\AI\ConversationMemory();
            }

            $memoria = $_SESSION['conversation_memory'];

            if ($limparTodas) {
                $memoria->limparTodas();
                $this->json([
                    'sucesso' => true,
                    'mensagem' => 'Todas as conversas foram limpas.',
                ]);
            } else {
                if (empty($id)) {
                    $memoria->limparConversaAtual();
                    $this->json([
                        'sucesso' => true,
                        'mensagem' => 'Conversa atual limpa.',
                    ]);
                } else {
                    if ($memoria->carregarConversa($id)) {
                        $memoria->limparConversaAtual();
                        $this->json([
                            'sucesso' => true,
                            'mensagem' => 'Conversa limpa.',
                        ]);
                    } else {
                        $this->json([
                            'sucesso' => false,
                            'mensagem' => 'Conversa não encontrada.',
                        ], 404);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao limpar conversa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * FASE 11: Exporta conversa para formato texto
     */
    public function exportarConversa(Request $request): void
    {
        try {
            $id = (string) $request->input('id', '');
            $formato = (string) $request->input('formato', 'texto');

            if (!isset($_SESSION['conversation_memory'])) {
                $_SESSION['conversation_memory'] = new \App\AI\ConversationMemory();
            }

            $memoria = $_SESSION['conversation_memory'];
            $conteúdo = $memoria->exportarParaPrompt($id);

            if (empty($conteúdo)) {
                $this->json([
                    'sucesso' => false,
                    'mensagem' => 'Conversa não encontrada.',
                ], 404);
                return;
            }

            if ($formato === 'json') {
                $info = $memoria->obterInfoConveraAtual();
                $mensagens = $memoria->obterMensagens(50);
                
                $this->json([
                    'sucesso' => true,
                    'mensagem' => 'Conversa exportada em JSON.',
                    'conversa' => $info,
                    'mensagens' => $mensagens,
                ]);
            } else {
                $this->json([
                    'sucesso' => true,
                    'mensagem' => 'Conversa exportada com sucesso.',
                    'conteúdo' => $conteúdo,
                    'formato' => 'texto',
                ]);
            }
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao exportar conversa: ' . $e->getMessage(),
            ], 500);
        }
    }
}