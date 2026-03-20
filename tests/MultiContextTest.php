<?php

declare(strict_types=1);

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

use App\AI\MultiContextManager;
use App\AI\ConversationMemory;

// Cores para output
$verde = "\033[32m";
$vermelho = "\033[31m";
$amarelo = "\033[33m";
$branco = "\033[0m";

$testes = 0;
$sucesso = 0;

function teste($nome, $condicao, $mensagem = '')
{
    global $testes, $sucesso, $verde, $vermelho, $amarelo, $branco;
    $testes++;

    if ($condicao) {
        echo "{$verde}✓ PASSOU{$branco} - {$nome}\n";
        $sucesso++;
    } else {
        echo "{$vermelho}✗ FALHOU{$branco} - {$nome}\n";
        if ($mensagem) {
            echo "  {$amarelo}→{$branco} {$mensagem}\n";
        }
    }
}

echo "\n========== TESTES FASE 11: MULTI-CONTEXT AI ==========\n\n";

// ====================
// TESTE 1: MultiContextManager - Análise de código
// ====================
echo "{$amarelo}Grupo 1: MultiContextManager{$branco}\n";

$manager = new MultiContextManager();
teste(
    'Instanciar MultiContextManager',
    $manager !== null,
    'Manager não foi criado'
);

// Criar arquivo de teste
$diretorioTeste = __DIR__ . '/../storage/test_codigo';
if (!is_dir($diretorioTeste)) {
    mkdir($diretorioTeste, 0755, true);
}

$arquivoTestePhp = $diretorioTeste . '/exemplo.php';
file_put_contents($arquivoTestePhp, '<?php
function calcular($a, $b) {
    return $a + $b;
}

class Calculadora {
    public function multiplicar($a, $b) {
        return $a * $b;
    }
}
?>');

$contexto = $manager->construirContextoMulti([
    'caminhos_código' => [$arquivoTestePhp],
    'caminhos_documentos' => [],
    'diretorio_raiz' => dirname(__DIR__),
]);

teste(
    'Construir contexto com arquivo código',
    isset($contexto['código']) && count($contexto['código']) > 0,
    'Contexto código vazio'
);

teste(
    'Contexto contém metadata',
    isset($contexto['metadata']) && isset($contexto['metadata']['tokens_utilizados']),
    'Metadata ausente'
);

teste(
    'Análise detecta funções',
    !empty($contexto['código'][0]['estrutura']['funcoes']) ?? false,
    'Funções não detectadas'
);

// ====================
// TESTE 2: MultiContextManager - Múltiplos arquivos
// ====================
echo "\n{$amarelo}Grupo 2: Análise Multi-Arquivo{$branco}\n";

$arquivo2 = $diretorioTeste . '/outro.php';
file_put_contents($arquivo2, '<?php
class Validador {
    public function validarEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
?>');

$contextoMulti = $manager->construirContextoMulti([
    'caminhos_código' => [$arquivoTestePhp, $arquivo2],
    'caminhos_documentos' => [],
]);

teste(
    'Processar múltiplos arquivos',
    count($contextoMulti['código']) >= 2,
    'Não processou todos os arquivos'
);

teste(
    'Manter limite de tokens',
    $contextoMulti['metadata']['tokens_utilizados'] <= $contextoMulti['metadata']['tokens_limite'],
    'Excedeu limite de tokens'
);

// ====================
// TESTE 3: Prompt enriquecido
// ====================
echo "\n{$amarelo}Grupo 3: Construção de Prompt{$branco}\n";

$promptOriginal = 'Como melhorar o desempenho desse código?';
$promptEnriquecido = $manager->construirPromptMultiContexto($promptOriginal, $contextoMulti);

teste(
    'Construir prompt enriquecido',
    !empty($promptEnriquecido) && strlen($promptEnriquecido) > strlen($promptOriginal),
    'Prompt não foi enriquecido'
);

teste(
    'Prompt contém contexto de código',
    strpos($promptEnriquecido, 'CÓDIGO') !== false || strpos($promptEnriquecido, 'arquivo') !== false,
    'Contexto de código não incluído'
);

teste(
    'Prompt contém tarefa solicitada',
    strpos($promptEnriquecido, $promptOriginal) !== false,
    'Prompt original não incluído'
);

// ====================
// TESTE 4: ConversationMemory - Inicialização
// ====================
echo "\n{$amarelo}Grupo 4: ConversationMemory{$branco}\n";

$memoria = new ConversationMemory();
teste(
    'Instanciar ConversationMemory',
    $memoria !== null,
    'Memória não foi criada'
);

$memoria->iniciarConversa('conversa_1', 'Teste Fase 11');
teste(
    'Iniciar nova conversa',
    $memoria->obterConveraAtual() === 'conversa_1',
    'Conversa não foi iniciada'
);

// ====================
// TESTE 5: ConversationMemory - Adicionar mensagens
// ====================
echo "\n{$amarelo}Grupo 5: Gerenciamento de Mensagens{$branco}\n";

$memoria->adicionarMensagem('user', 'Qual é o resultado de 2 + 2?');
$memoria->adicionarMensagem('assistant', 'O resultado de 2 + 2 é 4.');

$mensagens = $memoria->obterMensagens();
teste(
    'Adicionar mensagens',
    count($mensagens) >= 2,
    'Mensagens não foram adicionadas'
);

teste(
    'Mensagem de usuário armazenada',
    $mensagens[0]['papel'] === 'user' && strpos($mensagens[0]['conteúdo'], '2 + 2') !== false,
    'Mensagem de usuário incorreta'
);

teste(
    'Mensagem de assistente armazenada',
    $mensagens[1]['papel'] === 'assistant' && strpos($mensagens[1]['conteúdo'], '4') !== false,
    'Mensagem de assistente incorreta'
);

// ====================
// TESTE 6: ConversationMemory - Contexto
// ====================
echo "\n{$amarelo}Grupo 6: Contexto de Conversa{$branco}\n";

$memoria->atualizarContexto([
    'arquivo_código' => 'exemplo.php',
    'arquivo_documento' => 'doc.pdf',
]);

$contextoConversa = $memoria->obterContexto();
teste(
    'Atualizar contexto da conversa',
    isset($contextoConversa['arquivo_código']) && $contextoConversa['arquivo_código'] === 'exemplo.php',
    'Contexto não foi atualizado'
);

teste(
    'Múltiplas informações de contexto',
    isset($contextoConversa['arquivo_documento']) && $contextoConversa['arquivo_documento'] === 'doc.pdf',
    'Contexto incompleto'
);

// ====================
// TESTE 7: ConversationMemory - Listar conversas
// ====================
echo "\n{$amarelo}Grupo 7: Gerenciamento de Conversas{$branco}\n";

$memoria->iniciarConversa('conversa_2', 'Segunda Conversa');
$memoria->adicionarMensagem('user', 'Teste 2');

$conversas = $memoria->listarConversas();
teste(
    'Listar múltiplas conversas',
    count($conversas) >= 2,
    'Conversas não listadas'
);

teste(
    'Informações de conversa',
    isset($conversas[0]['titulo']) && isset($conversas[0]['criada_em']),
    'Informações incompletas'
);

// ====================
// TESTE 8: ConversationMemory - Carregamento
// ====================
echo "\n{$amarelo}Grupo 8: Carregamento de Conversas{$branco}\n";

$carregado = $memoria->carregarConversa('conversa_1');
teste(
    'Carregar conversa específica',
    $carregado === true && $memoria->obterConveraAtual() === 'conversa_1',
    'Conversa não foi carregada'
);

$infoConversa = $memoria->obterInfoConveraAtual();
teste(
    'Obter informações da conversa carregada',
    isset($infoConversa['id']) && $infoConversa['id'] === 'conversa_1',
    'Informações da conversa incorretas'
);

// ====================
// TESTE 9: ConversationMemory - Exportação
// ====================
echo "\n{$amarelo}Grupo 9: Exportação de Conversas{$branco}\n";

$exportação = $memoria->exportarParaPrompt('conversa_1');
teste(
    'Exportar conversa para texto',
    !empty($exportação) && strlen($exportação) > 0,
    'Exportação vazia'
);

teste(
    'Exportação contém histórico',
    strpos($exportação, 'CONVERSA') !== false && (strpos($exportação, 'conversa_1') !== false || strpos($exportação, 'Teste') !== false),
    'Histórico não incluído'
);

teste(
    'Exportação contém mensagens',
    strpos($exportação, 'User') !== false || strpos($exportação, 'user') !== false,
    'Mensagens não incluídas'
);

// ====================
// TESTE 10: ConversationMemory - Limpeza
// ====================
echo "\n{$amarelo}Grupo 10: Limpeza de Dados{$branco}\n";

$memoria->iniciarConversa('temp_1', 'Temp');
$memoria->adicionarMensagem('user', 'Teste temporário');
$memoria->limparConversaAtual();

$mensagensLimpas = $memoria->obterMensagens();
teste(
    'Limpar conversa atual',
    count($mensagensLimpas) === 0,
    'Mensagens não foram limpas'
);

$memoria->limparTodas();
$todasConversas = $memoria->listarConversas();
teste(
    'Limpar todas as conversas',
    count($todasConversas) === 0,
    'Conversas não foram limpas'
);

// ====================
// TESTE 11: Integração - Fluxo completo
// ====================
echo "\n{$amarelo}Grupo 11: Fluxo Completo Multi-Context{$branco}\n";

$manager2 = new MultiContextManager();
$memoria2 = new ConversationMemory();

// Criar documentação de teste
$diretorioDoc = __DIR__ . '/../storage/test_docs';
if (!is_dir($diretorioDoc)) {
    mkdir($diretorioDoc, 0755, true);
}

$docTeste = $diretorioDoc . '/guia.txt';
file_put_contents($docTeste, 'Guia de Desenvolvimento

Este é um guia para desenvolvedores que trabalham com PHP.

Seções:
1. Instalação
2. Configuração
3. Uso Básico
4. Exemplos avançados
');

$fluxoCompleto = $manager2->construirContextoMulti([
    'caminhos_código' => [$arquivoTestePhp, $arquivo2],
    'caminhos_documentos' => [$docTeste],
    'diretorio_raiz' => dirname(__DIR__),
    'incluir_estrutura' => false,
]);

teste(
    'Fluxo completo: código + documentos',
    !empty($fluxoCompleto['código']) && !empty($fluxoCompleto['documentos']),
    'Não processou código e documentos'
);

$memoria2->iniciarConversa('fluxo_1', 'Fluxo Completo');
$promptFluxo = 'Considere o código e a documentação fornecidos. Qual é a melhor prática?';
$memoria2->adicionarMensagem('user', $promptFluxo, $fluxoCompleto);

$promptEnriquecidoFluxo = $manager2->construirPromptMultiContexto($promptFluxo, $fluxoCompleto);
teste(
    'Enriquecer prompt com contexto completo',
    strlen($promptEnriquecidoFluxo) > strlen($promptFluxo) && strpos($promptEnriquecidoFluxo, 'CÓDIGO') !== false,
    'Prompt não foi corretamente enriquecido'
);

// ====================
// TESTE 12: Estimação de tokens
// ====================
echo "\n{$amarelo}Grupo 12: Gerenciamento de Tokens{$branco}\n";

$memoria2->adicionarMensagem('assistant', 'A melhor prática é usar validação de entrada em todos os endpoints da API.');

$info = $memoria2->obterInfoConveraAtual();
teste(
    'Estimar tokens da conversa',
    isset($info['tokens_estimados']) && $info['tokens_estimados'] > 0,
    'Estimação de tokens falhou'
);

teste(
    'Tokens estimados razoáveis',
    $info['tokens_estimados'] < 5000,
    'Estimação de tokens parece incorreta'
);

// ====================
// TESTE 13: Histórico com limite
// ====================
echo "\n{$amarelo}Grupo 13: Limite de Histórico{$branco}\n";

$memoria3 = new ConversationMemory();
$memoria3->iniciarConversa();

for ($i = 0; $i < 60; $i++) {
    $memoria3->adicionarMensagem('user', "Mensagem $i");
    $memoria3->adicionarMensagem('assistant', "Resposta $i");
}

$mensagensArmazenadas = $memoria3->obterMensagens(100);
teste(
    'Respeitar limite de armazenamento',
    count($mensagensArmazenadas) <= 50,
    'Limite de armazenamento excedido'
);

// ====================
// Limpeza
// ====================
echo "\n{$amarelo}Limpeza de arquivos de teste{$branco}\n";

array_map('unlink', glob($diretorioTeste . '/*'));
@rmdir($diretorioTeste);
array_map('unlink', glob($diretorioDoc . '/*'));
@rmdir($diretorioDoc);

teste(
    'Remover arquivos temporários',
    !file_exists($arquivoTestePhp),
    'Arquivos não foram removidos'
);

// ====================
// RESUMO
// ====================
echo "\n";
echo "=".str_repeat("=", 47)."=\n";
echo "RESUMO: {$sucesso}/{$testes} testes passaram\n";
if ($sucesso === $testes) {
    echo "{$verde}✓ TODOS OS TESTES PASSARAM!{$branco}\n";
} else {
    echo "{$vermelho}✗ Alguns testes falharam{$branco}\n";
}
echo "=".str_repeat("=", 47)."=\n\n";

exit($sucesso === $testes ? 0 : 1);
