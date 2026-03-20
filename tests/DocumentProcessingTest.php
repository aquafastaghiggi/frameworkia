<?php

declare(strict_types=1);

namespace Tests;

use App\Documents\DocumentManager;
use App\Documents\DocumentIndexer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocumentProcessingTest extends TestCase
{
    protected DocumentManager $documentManager;
    protected DocumentIndexer $documentIndexer;
    protected string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/frameworkia_test_' . uniqid();
        mkdir($this->testDir, 0755, true);

        $this->documentManager = new DocumentManager();
        $this->documentIndexer = new DocumentIndexer($this->documentManager);
    }

    protected function tearDown(): void
    {
        // Limpar arquivos de teste
        if (is_dir($this->testDir)) {
            array_map('unlink', glob($this->testDir . '/*.*'));
            rmdir($this->testDir);
        }
    }

    protected function criarArquivoTeste(string $nome, string $conteudo): string
    {
        $caminho = $this->testDir . '/' . $nome;
        file_put_contents($caminho, $conteudo);
        return $caminho;
    }

    public function testLerArquivoTexto(): void
    {
        $conteudo = "Olá mundo!\nEste é um arquivo de teste.";
        $caminho = $this->criarArquivoTeste('teste.txt', $conteudo);

        $resultado = $this->documentManager->ler($caminho);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('conteudo', $resultado);
        $this->assertStringContainsString('Olá mundo', $resultado['conteudo']);
    }

    public function testLerArquivoCSV(): void
    {
        $csv = "nome,idade,email\nJoão,30,joao@test.com\nMaria,25,maria@test.com";
        $caminho = $this->criarArquivoTeste('dados.csv', $csv);

        $resultado = $this->documentManager->ler($caminho);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('dados', $resultado);
        $this->assertCount(2, $resultado['dados']);
        $this->assertEquals('João', $resultado['dados'][0]['nome'] ?? null);
    }

    public function testExtrairMetadados(): void
    {
        $conteudo = "Conteúdo do arquivo";
        $caminho = $this->criarArquivoTeste('doc.txt', $conteudo);

        $resultado = $this->documentManager->extrairMetadados($caminho);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('arquivo', $resultado);
        $this->assertArrayHasKey('extensao', $resultado);
        $this->assertArrayHasKey('tamanho', $resultado);
        $this->assertEquals('doc.txt', $resultado['arquivo']);
        $this->assertEquals('txt', $resultado['extensao']);
    }

    public function testBuscarEmArquivo(): void
    {
        $conteudo = "PHP é ótimo\nFrameworkia é um IDE\nPHP com IA";
        $caminho = $this->criarArquivoTeste('busca.txt', $conteudo);

        $resultado = $this->documentManager->buscar($caminho, 'PHP');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('busca', $resultado);
        $this->assertGreater(0, count($resultado['busca'] ?? []));
    }

    public function testIndexarDocumento(): void
    {
        $conteudo = "Framework inteligente para desenvolvimento web";
        $caminho = $this->criarArquivoTeste('framework.txt', $conteudo);

        $resultado = $this->documentIndexer->indexarDocumento($caminho);

        $this->assertTrue($resultado['sucesso']);
        $this->assertArrayHasKey('dados', $resultado);
        $this->assertArrayHasKey('hash_verificacao', $resultado['dados']);
    }

    public function testBuscarNoIndice(): void
    {
        $conteudo1 = "Desenvolvimento de aplicações web";
        $conteudo2 = "Análise de dados com Excel";
        $caminho1 = $this->criarArquivoTeste('web.txt', $conteudo1);
        $caminho2 = $this->criarArquivoTeste('dados.txt', $conteudo2);

        $this->documentIndexer->indexarDocumento($caminho1);
        $this->documentIndexer->indexarDocumento($caminho2);

        $resultado = $this->documentIndexer->buscarNoIndice('desenvolvimento');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('termo_busca', $resultado);
        $this->assertArrayHasKey('total_resultados', $resultado);
    }

    public function testObtenerEstatisticas(): void
    {
        $caminho1 = $this->criarArquivoTeste('doc1.txt', 'Conteúdo 1');
        $caminho2 = $this->criarArquivoTeste('doc2.txt', 'Conteúdo 2');

        $this->documentIndexer->indexarDocumento($caminho1);
        $this->documentIndexer->indexarDocumento($caminho2);

        $stats = $this->documentIndexer->obterEstatisticas();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_documentos', $stats);
        $this->assertEquals(2, $stats['total_documentos']);
    }

    public function testTiposSuportados(): void
    {
        $tipos = $this->documentManager->tiposSuportados();

        $this->assertIsArray($tipos);
        $this->assertArrayHasKey('suportados', $tipos);
        $this->assertArrayHasKey('tipos', $tipos);
        $this->assertContains('txt', $tipos['suportados']);
        $this->assertContains('csv', $tipos['suportados']);
        $this->assertContains('pdf', $tipos['suportados']);
    }

    public function testLerMultiplos(): void
    {
        $caminho1 = $this->criarArquivoTeste('teste1.txt', 'Conteúdo 1');
        $caminho2 = $this->criarArquivoTeste('teste2.txt', 'Conteúdo 2');

        $resultados = $this->documentManager->lerMultiplos([$caminho1, $caminho2]);

        $this->assertCount(2, $resultados);
        $this->assertTrue($resultados[0]['sucesso']);
        $this->assertTrue($resultados[1]['sucesso']);
    }

    public function testGerarResumo(): void
    {
        $conteudo = "Este é um texto longo que será resumido para teste. " . str_repeat("a", 500);
        $caminho = $this->criarArquivoTeste('longo.txt', $conteudo);

        $resumo = $this->documentManager->gerarResumo($caminho, 100);

        $this->assertIsArray($resumo);
        $this->assertArrayHasKey('resumo', $resumo);
        $this->assertTrue($resumo['truncado']);
        $this->assertStringEndsWith('...', $resumo['resumo']);
    }

    public function testArquivoNaoSuportado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tipo de arquivo não suportado');

        $caminho = $this->criarArquivoTeste('teste.bin', 'conteudo binário');
        $this->documentManager->ler($caminho);
    }

    public function testArquivoNaoEncontrado(): void
    {
        $this->expectException(RuntimeException::class);

        $this->documentManager->ler('/arquivo/inexistente.txt');
    }

    public function testValidarIntegridade(): void
    {
        $caminho = $this->criarArquivoTeste('integridade.txt', 'Conteúdo');
        $this->documentIndexer->indexarDocumento($caminho);

        $resultado = $this->documentIndexer->validarIntegridade();

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('documentos_validos', $resultado);
        $this->assertEquals(1, $resultado['documentos_validos']);
    }

    public function testListarDocumentos(): void
    {
        $caminho1 = $this->criarArquivoTeste('doc1.txt', 'Conteúdo 1');
        $caminho2 = $this->criarArquivoTeste('doc2.txt', 'Conteúdo 2');

        $this->documentIndexer->indexarDocumento($caminho1);
        $this->documentIndexer->indexarDocumento($caminho2);

        $lista = $this->documentIndexer->listarDocumentos();

        $this->assertIsArray($lista);
        $this->assertArrayHasKey('documentos', $lista);
        $this->assertEquals(2, $lista['total']);
    }
}
