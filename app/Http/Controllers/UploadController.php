<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Uploads\UploadService;
use App\Documents\DocumentManager;
use App\Documents\DocumentIndexer;
use App\Documents\Intelligence\DocumentAnalyzer;
use App\Documents\Intelligence\EntityExtractor;
use App\Documents\Intelligence\DataTypeInferrer;
use RuntimeException;

class UploadController extends Controller
{
    protected UploadService $uploads;
    protected DocumentManager $documentManager;
    protected DocumentIndexer $documentIndexer;
    protected DocumentAnalyzer $analyzer;
    protected EntityExtractor $entityExtractor;
    protected DataTypeInferrer $typeInferrer;

    public function __construct(View $view, Response $response)
    {
        parent::__construct($view, $response);

        $basePath = dirname(__DIR__, 3);
        $this->uploads = new UploadService($basePath);
        $this->documentManager = new DocumentManager();
        $this->documentIndexer = new DocumentIndexer($this->documentManager);
        $this->analyzer = new DocumentAnalyzer($this->documentManager);
        $this->entityExtractor = new EntityExtractor();
        $this->typeInferrer = new DataTypeInferrer();
    }

    public function upload(Request $request): void
    {
        try {
            if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
                throw new RuntimeException('Nenhum arquivo enviado.');
            }

            $uploaded = $this->uploads->upload($_FILES['attachment']);

            if (!isset($_SESSION['uploaded_attachments']) || !is_array($_SESSION['uploaded_attachments'])) {
                $_SESSION['uploaded_attachments'] = [];
            }

            array_unshift($_SESSION['uploaded_attachments'], $uploaded);
            $_SESSION['uploaded_attachments'] = array_slice($_SESSION['uploaded_attachments'], 0, 20);

            $this->json([
                'success' => true,
                'message' => 'Arquivo enviado com sucesso.',
                'file' => $uploaded,
                'attachments' => $_SESSION['uploaded_attachments'],
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function delete(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');

            if ($relativePath === '') {
                throw new RuntimeException('Arquivo não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (is_file($fullPath)) {
                @unlink($fullPath);
            }

            if (isset($_SESSION['uploaded_attachments']) && is_array($_SESSION['uploaded_attachments'])) {
                $_SESSION['uploaded_attachments'] = array_values(array_filter(
                    $_SESSION['uploaded_attachments'],
                    fn($a) => ($a['relative_path'] ?? '') !== $relativePath
                ));
            }

            $this->json([
                'success' => true,
                'message' => 'Anexo removido.',
                'attachments' => $_SESSION['uploaded_attachments'] ?? [],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Lê conteúdo de documento
     * GET /attachments/content?path=caminho/arquivo.pdf
     */
    public function lerConteudo(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');

            if ($relativePath === '') {
                throw new RuntimeException('Caminho não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (!file_exists($fullPath)) {
                throw new RuntimeException('Arquivo não encontrado.');
            }

            $conteudo = $this->documentManager->ler($fullPath);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Conteúdo lido com sucesso',
                'dados' => $conteudo,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Extrai metadados de documento
     * GET /attachments/metadata?path=caminho/arquivo.pdf
     */
    public function obterMetadados(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');

            if ($relativePath === '') {
                throw new RuntimeException('Caminho não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (!file_exists($fullPath)) {
                throw new RuntimeException('Arquivo não encontrado.');
            }

            $metadados = $this->documentManager->extrairMetadados($fullPath);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Metadados extraídos com sucesso',
                'dados' => $metadados,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Busca em documentos
     * POST /attachments/search
     * Body: { "termo": "palavra", "caminhos": ["path1", "path2"] }
     */
    public function buscar(Request $request): void
    {
        try {
            $termo = (string) $request->input('termo');
            $caminhos = (array) $request->input('caminhos', []);

            if ($termo === '') {
                throw new RuntimeException('Termo de busca não informado.');
            }

            if (empty($caminhos)) {
                throw new RuntimeException('Nenhum arquivo especificado.');
            }

            $basePath = dirname(__DIR__, 3);
            $caminhosFull = array_map(
                fn($p) => $basePath . '/' . ltrim(str_replace('\\', '/', $p), '/'),
                $caminhos
            );

            $resultados = $this->documentManager->buscarMultiplos($caminhosFull, $termo);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Busca completada',
                'termo' => $termo,
                'total_arquivos' => count($caminhos),
                'dados' => $resultados,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Indexa documentos para busca rápida
     * POST /attachments/indexar
     * Body: { "caminhos": ["path1", "path2"] }
     */
    public function indexar(Request $request): void
    {
        try {
            $caminhos = (array) $request->input('caminhos', []);

            if (empty($caminhos)) {
                throw new RuntimeException('Nenhum arquivo especificado.');
            }

            $basePath = dirname(__DIR__, 3);
            $caminhosFull = array_map(
                fn($p) => $basePath . '/' . ltrim(str_replace('\\', '/', $p), '/'),
                $caminhos
            );

            $resultados = $this->documentIndexer->indexarMultiplos($caminhosFull);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Indexação completada',
                'total_arquivos' => count($caminhos),
                'dados' => $resultados,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Busca no índice
     * GET /attachments/buscar-indice?termo=palavra
     */
    public function buscarNoIndice(Request $request): void
    {
        try {
            $termo = (string) $request->input('termo');

            if ($termo === '') {
                throw new RuntimeException('Termo de busca não informado.');
            }

            $resultado = $this->documentIndexer->buscarNoIndice($termo);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Busca no índice completada',
                'dados' => $resultado,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obtém estatísticas do índice
     * GET /attachments/estatisticas
     */
    public function obterEstatisticas(Request $request): void
    {
        try {
            $stats = $this->documentIndexer->obterEstatisticas();

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Estatísticas obtidas',
                'dados' => $stats,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Lista documentos indexados
     * GET /attachments/lista-documentos
     */
    public function listarDocumentos(Request $request): void
    {
        try {
            $lista = $this->documentIndexer->listarDocumentos();

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Lista de documentos',
                'dados' => $lista,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Tipos de arquivo suportados
     * GET /attachments/tipos-suportados
     */
    public function tiposSuportados(Request $request): void
    {
        try {
            $tipos = $this->documentManager->tiposSuportados();

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Tipos suportados',
                'dados' => $tipos,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Analisa documento inteligentemente
     * GET /attachments/analisar?path=caminho/arquivo.txt
     */
    public function analisar(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');

            if ($relativePath === '') {
                throw new RuntimeException('Caminho não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (!file_exists($fullPath)) {
                throw new RuntimeException('Arquivo não encontrado.');
            }

            $analise = $this->analyzer->analisar($fullPath);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Análise completada',
                'dados' => $analise,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Extrai entidades de documento
     * GET /attachments/entidades?path=caminho/arquivo.txt
     */
    public function extrairEntidades(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');

            if ($relativePath === '') {
                throw new RuntimeException('Caminho não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (!file_exists($fullPath)) {
                throw new RuntimeException('Arquivo não encontrado.');
            }

            $conteudo = file_get_contents($fullPath);
            if ($conteudo === false) {
                throw new RuntimeException('Falha ao ler arquivo.');
            }

            $entidades = $this->entityExtractor->extrairEntidades($conteudo);
            $hierarquia = $this->entityExtractor->extrairHierarquia($conteudo);
            $listas = $this->entityExtractor->extrairListas($conteudo);
            $topicos = $this->entityExtractor->extrairTopicos($conteudo);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Entidades extraídas',
                'dados' => [
                    'entidades' => $entidades,
                    'hierarquia' => $hierarquia,
                    'listas' => $listas,
                    'topicos' => $topicos,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Detecta tipos de dados
     * POST /attachments/detectar-tipos
     * Body: { "dados": [[...], [...]], "cabecalhos": [...] }
     */
    public function detectarTipos(Request $request): void
    {
        try {
            $dados = (array) $request->input('dados', []);
            $cabecalhos = (array) $request->input('cabecalhos', []);

            if (empty($dados)) {
                throw new RuntimeException('Dados não informados.');
            }

            $analise = $this->typeInferrer->analisarEstrutura($dados, $cabecalhos);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Tipos detectados',
                'dados' => $analise,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Detecta padrões em dados
     * POST /attachments/detectar-padroes
     * Body: { "valores": [...] }
     */
    public function detectarPadroes(Request $request): void
    {
        try {
            $valores = (array) $request->input('valores', []);

            if (empty($valores)) {
                throw new RuntimeException('Valores não informados.');
            }

            $padroes = $this->typeInferrer->detectarPadroes($valores);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Padrões detectados',
                'dados' => $padroes,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Detecta idioma do documento
     * GET /attachments/detectar-idioma?path=caminho/arquivo.txt
     */
    public function detectarIdioma(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');

            if ($relativePath === '') {
                throw new RuntimeException('Caminho não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (!file_exists($fullPath)) {
                throw new RuntimeException('Arquivo não encontrado.');
            }

            $idioma = $this->analyzer->detectarIdioma($fullPath);

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Idioma detectado',
                'dados' => $idioma,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Gera resumo automático
     * GET /attachments/resumo-automatico?path=caminho/arquivo.txt&sentencas=3
     */
    public function gerarResumo(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');
            $sentencas = (int) $request->input('sentencas', 3);

            if ($relativePath === '') {
                throw new RuntimeException('Caminho não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (!file_exists($fullPath)) {
                throw new RuntimeException('Arquivo não encontrado.');
            }

            $resumo = $this->analyzer->gerarResumoAutomatico($fullPath, max(1, $sentencas));

            $this->json([
                'sucesso' => true,
                'mensagem' => 'Resumo gerado',
                'dados' => $resumo,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'sucesso' => false,
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }
}