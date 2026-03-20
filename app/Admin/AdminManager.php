<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Admin Manager para Painel Administrativo
 * 
 * Responsável por:
 * - Gerenciamento de usuários
 * - Multi-workspace
 * - Versionamento do sistema
 * - Configurações globais
 * - Relatórios
 */
class AdminManager
{
    private array $usuários = [];
    private array $workspaces = [];
    private array $versão = [
        'major' => 1,
        'minor' => 0,
        'patch' => 0,
        'pré_release' => null,
    ];
    private array $configurações = [];
    private array $auditoria = [];

    public function __construct()
    {
        $this->inicializarConfiguração();
        $this->carregar();
    }

    /**
     * Inicializa configurações padrão
     */
    private function inicializarConfiguração(): void
    {
        $this->configurações = [
            'modo_manutenção' => false,
            'permitir_novos_usuários' => true,
            'permitir_novos_workspaces' => true,
            'limite_usuários' => 100,
            'limite_workspaces_por_usuário' => 10,
            'limite_armazenamento_gb' => 10,
            'limite_requisições_minuto' => 100,
            'autenticação_obrigatória' => true,
            'força_https' => false,
            'session_timeout_minutos' => 60,
            'logs_retenção_dias' => 30,
        ];
    }

    /**
     * Cria novo usuário
     */
    public function criarUsuário(string $email, string $senha, string $nome = ''): array
    {
        if (count($this->usuários) >= $this->configurações['limite_usuários']) {
            return ['sucesso' => false, 'erro' => 'Limite de usuários atingido'];
        }

        if (isset($this->usuários[$email])) {
            return ['sucesso' => false, 'erro' => 'Usuário já existe'];
        }

        $idUsuário = 'user_' . uniqid();

        $this->usuários[$email] = [
            'id' => $idUsuário,
            'email' => $email,
            'nome' => $nome,
            'senha_hash' => password_hash($senha, PASSWORD_ARGON2ID),
            'criado_em' => date('Y-m-d H:i:s'),
            'último_acesso' => null,
            'ativo' => true,
            'rol' => 'usuário', // 'admin', 'usuário', 'editor'
            'workspaces' => [],
        ];

        $this->registrarAuditoria('criar_usuário', $email, ['novo_usuário_id' => $idUsuário]);
        $this->salvar();

        return [
            'sucesso' => true,
            'usuário_id' => $idUsuário,
            'email' => $email,
        ];
    }

    /**
     * Verifica credenciais
     */
    public function verificarCredenciais(string $email, string $senha): array
    {
        if (!isset($this->usuários[$email])) {
            return ['autenticado' => false, 'erro' => 'Usuário não encontrado'];
        }

        $usuário = $this->usuários[$email];

        if (!$usuário['ativo']) {
            return ['autenticado' => false, 'erro' => 'Usuário desativado'];
        }

        if (!password_verify($senha, $usuário['senha_hash'])) {
            return ['autenticado' => false, 'erro' => 'Senha incorreta'];
        }

        // Atualizar último acesso
        $this->usuários[$email]['último_acesso'] = date('Y-m-d H:i:s');
        $this->registrarAuditoria('login', $email, []);
        $this->salvar();

        return [
            'autenticado' => true,
            'usuário_id' => $usuário['id'],
            'email' => $email,
            'rol' => $usuário['rol'],
        ];
    }

    /**
     * Cria novo workspace
     */
    public function criarWorkspace(string $emailProprietário, string $nome, string $caminho): array
    {
        if (!isset($this->usuários[$emailProprietário])) {
            return ['sucesso' => false, 'erro' => 'Usuário não encontrado'];
        }

        $usuário = $this->usuários[$emailProprietário];

        if (count($usuário['workspaces']) >= $this->configurações['limite_workspaces_por_usuário']) {
            return ['sucesso' => false, 'erro' => 'Limite de workspaces atingido'];
        }

        $idWorkspace = 'ws_' . uniqid();

        $this->workspaces[$idWorkspace] = [
            'id' => $idWorkspace,
            'nome' => $nome,
            'caminho' => $caminho,
            'proprietário' => $emailProprietário,
            'membros' => [$emailProprietário => 'proprietário'],
            'criado_em' => date('Y-m-d H:i:s'),
            'armazenamento_usado_mb' => 0,
            'ativo' => true,
        ];

        $this->usuários[$emailProprietário]['workspaces'][] = $idWorkspace;

        $this->registrarAuditoria('criar_workspace', $emailProprietário, [
            'workspace_id' => $idWorkspace,
            'nome' => $nome,
        ]);
        $this->salvar();

        return [
            'sucesso' => true,
            'workspace_id' => $idWorkspace,
            'nome' => $nome,
        ];
    }

    /**
     * Lista workspaces do usuário
     */
    public function listarWorkspacesDoUsuário(string $email): array
    {
        if (!isset($this->usuários[$email])) {
            return [];
        }

        $usuário = $this->usuários[$email];
        $resultado = [];

        foreach ($usuário['workspaces'] as $idWorkspace) {
            if (isset($this->workspaces[$idWorkspace])) {
                $resultado[] = $this->workspaces[$idWorkspace];
            }
        }

        return $resultado;
    }

    /**
     * Incrementa versão
     */
    public function incrementarVersão(string $tipo = 'patch'): array
    {
        // $tipo: 'major', 'minor', 'patch'
        
        if ($tipo === 'major') {
            $this->versão['major']++;
            $this->versão['minor'] = 0;
            $this->versão['patch'] = 0;
        } elseif ($tipo === 'minor') {
            $this->versão['minor']++;
            $this->versão['patch'] = 0;
        } else {
            $this->versão['patch']++;
        }

        $this->registrarAuditoria('versionamento', 'sistema', [
            'nova_versão' => $this->obterVersão(),
            'tipo' => $tipo,
        ]);
        $this->salvar();

        return ['nova_versão' => $this->obterVersão()];
    }

    /**
     * Obtém versão atual
     */
    public function obterVersão(): string
    {
        $v = $this->versão;
        $versão = "{$v['major']}.{$v['minor']}.{$v['patch']}";
        
        if ($v['pré_release']) {
            $versão .= "-{$v['pré_release']}";
        }

        return $versão;
    }

    /**
     * Define configuração
     */
    public function definirConfiguração(string $chave, $valor): bool
    {
        if (!isset($this->configurações[$chave])) {
            return false;
        }

        $this->configurações[$chave] = $valor;
        $this->registrarAuditoria('alterar_configuração', 'sistema', [
            'chave' => $chave,
            'valor_anterior' => $this->configurações[$chave],
            'valor_novo' => $valor,
        ]);
        $this->salvar();

        return true;
    }

    /**
     * Obtém configurações
     */
    public function obterConfigurações(): array
    {
        return $this->configurações;
    }

    /**
     * Ativa modo manutenção
     */
    public function ativarModoManutenção(string $mensagem = ''): void
    {
        $this->configurações['modo_manutenção'] = true;
        $this->registrarAuditoria('modo_manutenção', 'sistema', [
            'mensagem' => $mensagem,
            'ativo' => true,
        ]);
        $this->salvar();
    }

    /**
     * Desativa modo manutenção
     */
    public function desativarModoManutenção(): void
    {
        $this->configurações['modo_manutenção'] = false;
        $this->registrarAuditoria('modo_manutenção', 'sistema', [
            'ativo' => false,
        ]);
        $this->salvar();
    }

    /**
     * Registra ação de auditoria
     */
    private function registrarAuditoria(string $ação, string $usuário, array $detalhes = []): void
    {
        $this->auditoria[] = [
            'id' => uniqid(),
            'ação' => $ação,
            'usuário' => $usuário,
            'detalhes' => $detalhes,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
        ];

        // Manter últimos 10000 logs
        if (count($this->auditoria) > 10000) {
            $this->auditoria = array_slice($this->auditoria, -10000);
        }
    }

    /**
     * Obtém logs de auditoria
     */
    public function obterAuditoria(string $filtroAção = '', int $limite = 100): array
    {
        $logs = $this->auditoria;

        if ($filtroAção) {
            $logs = array_filter($logs, fn($l) => strpos($l['ação'], $filtroAção) !== false);
        }

        return array_slice(array_reverse($logs), 0, $limite);
    }

    /**
     * Gera relatório do sistema
     */
    public function gerarRelatório(): array
    {
        return [
            'versão' => $this->obterVersão(),
            'usuários' => [
                'total' => count($this->usuários),
                'ativos' => count(array_filter($this->usuários, fn($u) => $u['ativo'])),
                'limite' => $this->configurações['limite_usuários'],
            ],
            'workspaces' => [
                'total' => count($this->workspaces),
                'ativos' => count(array_filter($this->workspaces, fn($w) => $w['ativo'])),
            ],
            'modo_manutenção' => $this->configurações['modo_manutenção'],
            'logs_auditoria' => count($this->auditoria),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Salva estado
     */
    private function salvar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['admin_manager'] = [
                'usuários' => $this->usuários,
                'workspaces' => $this->workspaces,
                'versão' => $this->versão,
                'configurações' => $this->configurações,
                'auditoria' => $this->auditoria,
            ];
        }
    }

    /**
     * Carrega estado
     */
    private function carregar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['admin_manager'])) {
            $data = $_SESSION['admin_manager'];
            $this->usuários = $data['usuários'] ?? [];
            $this->workspaces = $data['workspaces'] ?? [];
            $this->versão = $data['versão'] ?? $this->versão;
            $this->configurações = $data['configurações'] ?? $this->configurações;
            $this->auditoria = $data['auditoria'] ?? [];
        }
    }
}
