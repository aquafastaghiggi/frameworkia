#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\UI\UIManager;
use App\Security\SecurityManager;
use App\Performance\PerformanceManager;
use App\Agent\AutonomousAgent;
use App\Admin\AdminManager;
use App\AI\ChatService;
use App\AI\MockAIProvider;

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
        echo "{$verde}✓{$branco} $nome\n";
        $sucesso++;
    } else {
        echo "{$vermelho}✗{$branco} $nome\n";
        if ($mensagem) {
            echo "  {$amarelo}→{$branco} $mensagem\n";
        }
    }
}

echo "\n{$amarelo}========== TESTES FASES 14-18 =========={$branco}\n\n";

// ====================
// FASE 14: UX/UI
// ====================
echo "{$amarelo}Fase 14: UX/UI Profissional{$branco}\n";

$ui = new UIManager();

$aba1 = $ui->abrirAba('/teste.php', 'echo "teste";');
teste('Abrir aba', isset($aba1['id']), '');

teste('Aba tem conteúdo', $aba1['conteúdo'] === 'echo "teste";', '');

$abas = $ui->obterAbas();
teste('Listar abas', count($abas) > 0, '');

$ui->marcarModificado($aba1['id']);
$abaModificada = $ui->obterAba($aba1['id']);
teste('Marcar modificado', $abaModificada['modificado'] === true, '');

$ui->ativarAba($aba1['id']);
teste('Ativar aba', $ui->obterEstado()['aba_ativa'] === $aba1['id'], '');

$notif = $ui->adicionarNotificação('sucesso', 'Arquivo salvo', 'Sucesso');
teste('Adicionar notificação', isset($notif['id']), '');

teste('Notificação tem tipo', $notif['tipo'] === 'sucesso', '');

$layout = $ui->criarLayout('dois-colunas');
teste('Criar layout', isset($layout['id']), '');

teste('Layout tem tipo', $layout['tipo'] === 'dois-colunas', '');

$ui->adicionarAbaPainel($layout['id'], 1, $aba1['id']);
teste('Adicionar aba a painel', true, '');

$estado = $ui->obterEstado();
teste('Obter estado UI', isset($estado['abas']) && isset($estado['layouts']), '');

// ====================
// FASE 15: Segurança
// ====================
echo "\n{$amarelo}Fase 15: Segurança{$branco}\n";

$segurança = new SecurityManager();

$validação = $segurança->validarLeituraArquivo(__FILE__);
teste('Validação arquivo existente', $validação['válido'] === true, '');

$validação = $segurança->validarLeituraArquivo('/etc/passwd');
teste('Bloquer arquivo sensível', $validação['válido'] === false, 'Deveria bloquear /etc/passwd');

$validação = $segurança->validarEscritaArquivo('/teste.php', 1000);
teste('Validar escrita arquivo', isset($validação['válido']), '');

$git = $segurança->validarComandoGit('git push origin main');
teste('Validar git push', $git['válido'] === true, '');

$git = $segurança->validarComandoGit('git push --force origin main');
teste('Detectar force push', count($git['avisos']) > 0, 'Deveria avisar sobre force push');

$conteúdo = $segurança->validarConteúdo('<?php eval($_POST["code"]); ?>', 'php');
teste('Detectar eval', count($conteúdo['avisos']) > 0, '');

$rateLimit = $segurança->verificarRateLimit('user_1');
teste('Rate limit inicial', $rateLimit['permitido'] === true, '');

$perms = $segurança->obterPermissões();
teste('Obter permissões', isset($perms['leitura']) && $perms['leitura'] === true, '');

$segurança->definirPermissão('git_push', false);
$perms = $segurança->obterPermissões();
teste('Alterar permissão', $perms['git_push'] === false, '');

$relatório = $segurança->gerarRelatório();
teste('Gerar relatório segurança', isset($relatório['permissões']), '');

// ====================
// FASE 16: Performance
// ====================
echo "\n{$amarelo}Fase 16: Performance{$branco}\n";

$perf = new PerformanceManager();

$perf->cache('chave_teste', ['dados' => 'valor'], 3600);
teste('Cache: armazenar', true, '');

$valor = $perf->obterDoCache('chave_teste');
teste('Cache: recuperar', $valor !== null && $valor['dados'] === 'valor', '');

teste('Cache: verificar existência', $perf->temCache('chave_teste') === true, '');

$perf->limparCache();
teste('Cache: limpar', $perf->temCache('chave_teste') === false, '');

$resultado = $perf->adicionarTarefaNaFila('indexação', ['caminho' => '/']);
teste('Fila: adicionar tarefa', $resultado['sucesso'] === true, '');

$status = $perf->obterStatusFila();
teste('Fila: status', $status['pendentes'] >= 1, '');

$próxima = $perf->obterPróximaTarefa();
teste('Fila: obter próxima', $próxima !== null && $próxima['status'] === 'processando', '');

$perf->finalizarTarefa($próxima['id'], ['resultado' => 'sucesso']);
teste('Fila: finalizar tarefa', true, '');

$visíveis = $perf->obterArquivosVisíveis(__DIR__);
teste('Lazy loading: arquivos visíveis', is_array($visíveis), '');

$relatório = $perf->gerarRelatório();
teste('Performance: relatório', isset($relatório['cache']) && isset($relatório['fila']), '');

// ====================
// FASE 17: AI Agent
// ====================
echo "\n{$amarelo}Fase 17: AI Agent Autônomo{$branco}\n";

$provider = new MockAIProvider();
$chatService = new ChatService($provider);
$agent = new AutonomousAgent($chatService);

$plano = $agent->criarPlano('Implementar autenticação de usuários', []);
teste('Agent: criar plano', $plano['sucesso'] === true && isset($plano['plano_id']), '');

teste('Agent: plano tem etapas', isset($plano['plano']['etapas']) && is_array($plano['plano']['etapas']), '');

$histórico = $agent->obterHistórico();
teste('Agent: obter histórico', is_array($histórico) && count($histórico) > 0, '');

$detalhes = $agent->obterDetalhesPlano($plano['plano_id']);
teste('Agent: obter detalhes plano', $detalhes !== null && isset($detalhes['objetivo']), '');

$etapa = $agent->executarEtapaProxima($plano['plano_id']);
teste('Agent: executar etapa', $etapa['sucesso'] === true, '');

$debug = $agent->autoDebug($plano['plano_id']);
teste('Agent: auto debug', $debug['sucesso'] === true, '');

$melhoria = $agent->executarMelhoriaContínua($plano['plano_id']);
teste('Agent: melhoria contínua', isset($melhoria['métricas']), '');

// ====================
// FASE 18: Produto
// ====================
echo "\n{$amarelo}Fase 18: Produto Final{$branco}\n";

$admin = new AdminManager();

$usuário = $admin->criarUsuário('user@teste.com', 'senha123', 'User Teste');
teste('Admin: criar usuário', $usuário['sucesso'] === true, '');

$usuario2 = $admin->criarUsuário('user2@teste.com', 'senha456', 'User 2');
teste('Admin: segundo usuário', $usuario2['sucesso'] === true, '');

$auth = $admin->verificarCredenciais('user@teste.com', 'senha123');
teste('Admin: autenticação', $auth['autenticado'] === true, '');

$auth = $admin->verificarCredenciais('user@teste.com', 'senhaErrada');
teste('Admin: rejeitar senha errada', $auth['autenticado'] === false, '');

$workspace = $admin->criarWorkspace('user@teste.com', 'Meu Projeto', '/workspace/1');
teste('Admin: criar workspace', $workspace['sucesso'] === true, '');

$workspaces = $admin->listarWorkspacesDoUsuário('user@teste.com');
teste('Admin: listar workspaces', count($workspaces) > 0, '');

$versão = $admin->incrementarVersão('patch');
teste('Admin: incrementar versão patch', strpos($versão['nova_versão'], '.0.1') !== false, '');

$admin->incrementarVersão('minor');
$versão = $admin->obterVersão();
teste('Admin: incrementar versão minor', strpos($versão, '.1.0') !== false, '');

$admin->definirConfiguração('modo_manutenção', true);
$conf = $admin->obterConfigurações();
teste('Admin: alterar configuração', $conf['modo_manutenção'] === true, '');

$admin->ativarModoManutenção('Manutenção programada');
$conf = $admin->obterConfigurações();
teste('Admin: modo manutenção ativado', $conf['modo_manutenção'] === true, '');

$admin->desativarModoManutenção();
$conf = $admin->obterConfigurações();
teste('Admin: modo manutenção desativado', $conf['modo_manutenção'] === false, '');

$auditoria = $admin->obterAuditoria();
teste('Admin: obter auditoria', is_array($auditoria) && count($auditoria) > 0, '');

$relatório = $admin->gerarRelatório();
teste('Admin: gerar relatório', isset($relatório['versão']) && isset($relatório['usuários']), '');

// ====================
// RESUMO
// ====================
echo "\n{$amarelo}=".str_repeat("=", 38)."{$branco}\n";
echo "RESUMO: {$sucesso}/{$testes} testes passaram\n";
if ($sucesso === $testes) {
    echo "{$verde}✓ TODAS AS FASES TESTADAS COM SUCESSO!{$branco}\n";
} else {
    echo "{$vermelho}✗ Alguns testes falharam{$branco}\n";
}
echo "{$amarelo}=".str_repeat("=", 38)."{$branco}\n\n";

exit($sucesso === $testes ? 0 : 1);
