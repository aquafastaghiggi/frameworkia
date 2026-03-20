<?php

declare(strict_types=1);

namespace Tests;

use App\Documents\DocumentManager;
use App\Documents\Intelligence\DocumentAnalyzer;
use App\Documents\Intelligence\EntityExtractor;
use App\Documents\Intelligence\DataTypeInferrer;
use PHPUnit\Framework\TestCase;

class DocumentIntelligenceTest extends TestCase
{
    protected DocumentAnalyzer $analyzer;
    protected EntityExtractor $extractor;
    protected DataTypeInferrer $inferrer;
    protected string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/frameworkia_test_' . uniqid();
        mkdir($this->testDir, 0755, true);

        $documentManager = new DocumentManager();
        $this->analyzer = new DocumentAnalyzer($documentManager);
        $this->extractor = new EntityExtractor();
        $this->inferrer = new DataTypeInferrer();
    }

    protected function tearDown(): void
    {
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

    // ============ TESTES ANALYZADOR ============

    public function testAnalisarDocumento(): void
    {
        $conteudo = "Este é um documento de teste. Ele contém várias sentenças. É muito interessante!";
        $caminho = $this->criarArquivoTeste('analise.txt', $conteudo);

        $resultado = $this->analyzer->analisar($caminho);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('analise', $resultado);
        $this->assertArrayHasKey('densidade_textual', $resultado['analise']);
        $this->assertArrayHasKey('complexidade', $resultado['analise']);
        $this->assertArrayHasKey('sentimento', $resultado['analise']);
    }

    public function testDetectarIdioma(): void
    {
        $conteudo = "O português é uma língua muito bonita. As palavras são claras e expressivas.";
        $caminho = $this->criarArquivoTeste('idioma.txt', $conteudo);

        $resultado = $this->analyzer->detectarIdioma($caminho);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('idioma', $resultado);
        $this->assertArrayHasKey('confianca', $resultado);
        $this->assertEquals('português', $resultado['idioma']);
    }

    public function testAnalisarSentimento(): void
    {
        $conteudo = "Adorei este produto! É excelente e maravilhoso. Muito bom mesmo!";
        $caminho = $this->criarArquivoTeste('sentimento.txt', $conteudo);

        $resultado = $this->analyzer->analisar($caminho);

        $this->assertEquals('Positivo', $resultado['analise']['sentimento']['sentimento']);
        $this->assertGreater(0, $resultado['analise']['sentimento']['score']);
    }

    public function testGerarResumoAutomatico(): void
    {
        $conteudo = "Primeira sentença do parágrafo inicial.\n\nSegunda sentença do segundo parágrafo.\n\nTerceira sentença do terceiro parágrafo.";
        $caminho = $this->criarArquivoTeste('resumo.txt', $conteudo);

        $resultado = $this->analyzer->gerarResumoAutomatico($caminho, 2);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('resumo', $resultado);
        $this->assertArrayHasKey('sentencas_selecionadas', $resultado);
    }

    public function testAvaliarQualidade(): void
    {
        $conteudo = "Este é um documento bem estruturado. Ele tem parágrafos bem divididos. As ideias são claras e objetivas.";
        $caminho = $this->criarArquivoTeste('qualidade.txt', $conteudo);

        $resultado = $this->analyzer->analisar($caminho);

        $this->assertArrayHasKey('qualidade', $resultado['analise']);
        $this->assertArrayHasKey('score_geral', $resultado['analise']['qualidade']);
        $this->assertArrayHasKey('status', $resultado['analise']['qualidade']);
    }

    // ============ TESTES ENTITY EXTRACTOR ============

    public function testExtrairEmails(): void
    {
        $texto = "Entre em contato conosco: contato@empresa.com ou suporte@empresa.com.br";

        $entidades = $this->extractor->extrairEntidades($texto);

        $this->assertGreater(0, count($entidades['emails']));
        $this->assertContains('contato@empresa.com', $entidades['emails']);
    }

    public function testExtrairDatas(): void
    {
        $texto = "O evento será em 25/12/2025 ou talvez 31/01/2026.";

        $entidades = $this->extractor->extrairEntidades($texto);

        $this->assertGreater(0, count($entidades['datas']));
        $this->assertContains('25/12/2025', $entidades['datas']);
    }

    public function testExtrairUrls(): void
    {
        $texto = "Visite https://www.exemplo.com.br para mais informações.";

        $entidades = $this->extractor->extrairEntidades($texto);

        $this->assertGreater(0, count($entidades['urls']));
    }

    public function testExtrairHierarquia(): void
    {
        $texto = "# Título Principal\n## Subtítulo\n### Sub-subtítulo\nConteúdo aqui.";

        $hierarquia = $this->extractor->extrairHierarquia($texto);

        $this->assertIsArray($hierarquia['hierarquia']);
        $this->assertGreater(0, count($hierarquia['hierarquia']));
    }

    public function testExtrairTopicos(): void
    {
        $texto = "Framework é muito importante. PHP é uma linguagem poderosa. Development com PHP é rápido.";

        $topicos = $this->extractor->extrairTopicos($texto, 5);

        $this->assertArrayHasKey('topicos', $topicos);
        $this->assertGreater(0, count($topicos['topicos']));
    }

    public function testExtrairListas(): void
    {
        $texto = "Pontos importantes:\n- Primeiro ponto\n- Segundo ponto\n- Terceiro ponto";

        $listas = $this->extractor->extrairListas($texto);

        $this->assertGreater(0, count($listas['listas']));
        $this->assertEquals('bullet', $listas['listas'][0]['tipo']);
    }

    // ============ TESTES DATA TYPE INFERRER ============

    public function testInferirTipoInteiro(): void
    {
        $tipo = $this->inferrer->inferirTipo('123');
        $this->assertEquals('inteiro', $tipo);
    }

    public function testInferirTipoDecimal(): void
    {
        $tipo = $this->inferrer->inferirTipo('123.45');
        $this->assertEquals('decimal', $tipo);
    }

    public function testInferirTipoEmail(): void
    {
        $tipo = $this->inferrer->inferirTipo('usuario@exemplo.com');
        $this->assertEquals('email', $tipo);
    }

    public function testInferirTipoData(): void
    {
        $tipo = $this->inferrer->inferirTipo('25/12/2025');
        $this->assertEquals('data', $tipo);
    }

    public function testInferirTipoBooleano(): void
    {
        $tipo = $this->inferrer->inferirTipo('sim');
        $this->assertEquals('booleano', $tipo);

        $tipo = $this->inferrer->inferirTipo('não');
        $this->assertEquals('booleano', $tipo);
    }

    public function testInferirTipoColuna(): void
    {
        $valores = ['1', '2', '3', '4', '5'];
        $resultado = $this->inferrer->inferirTipoColuna($valores);

        $this->assertArrayHasKey('tipo_predominante', $resultado);
        $this->assertEquals('inteiro', $resultado['tipo_predominante']);
    }

    public function testAnalisarEstrutura(): void
    {
        $dados = [
            ['João', '30', 'joao@teste.com'],
            ['Maria', '25', 'maria@teste.com'],
            ['Pedro', '35', 'pedro@teste.com'],
        ];
        $cabecalhos = ['Nome', 'Idade', 'Email'];

        $resultado = $this->inferrer->analisarEstrutura($dados, $cabecalhos);

        $this->assertEquals(3, $resultado['total_linhas']);
        $this->assertEquals(3, $resultado['total_colunas']);
        $this->assertGreater(0, count($resultado['colunas']));
    }

    public function testDetectarPadroes(): void
    {
        $valores = ['1', '2', '3', '4', '5'];

        $resultado = $this->inferrer->detectarPadroes($valores);

        $this->assertArrayHasKey('padroes_detectados', $resultado);
        $this->assertArrayHasKey('valores_unicos', $resultado);
    }

    public function testValidarCpf(): void
    {
        // CPF válido: 111.444.777-35
        $tipo = $this->inferrer->inferirTipo('11144477735');
        $this->assertEquals('cpf_cnpj', $tipo);
    }

    public function testInferirTipoCurrencia(): void
    {
        $tipo = $this->inferrer->inferirTipo('R$ 1.234,50');
        $this->assertEquals('moeda', $tipo);
    }

    public function testAnalisarMultiplosDocumentos(): void
    {
        $arquivo1 = $this->criarArquivoTeste('doc1.txt', 'Conteúdo 1');
        $arquivo2 = $this->criarArquivoTeste('doc2.txt', 'Conteúdo 2');

        $resultado = $this->analyzer->analisarMultiplos([$arquivo1, $arquivo2]);

        $this->assertCount(2, $resultado);
        $this->assertTrue($resultado[0]['sucesso']);
        $this->assertTrue($resultado[1]['sucesso']);
    }
}
