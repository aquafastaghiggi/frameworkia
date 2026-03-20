<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Gerenciador de Segurança Avançada
 * 
 * Responsável por:
 * - Sandbox de arquivos (validação rigorosa)
 * - Controle de permissões
 * - Bloqueio de comandos perigosos
 * - Rate limiting
 * - Validação de Git commands
 */
class SecurityManager
{
    private array $permissões = [];
    private array $comandosBloqueados = [
        'rm -rf /',
        'format c:',
        'dd if=/dev/zero',
        'mkfs',
        '>;',
        'eval',
        'exec',
        'passthru',
        'system',
        'shell_exec',
        'proc_open',
        '`',
    ];

    private array $extensõesInseguras = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'scr', 'vbs', 'js', 'zip'
    ];

    private array $caminhosBloqueados = [
        '/etc/passwd',
        '/etc/shadow',
        'C:\\Windows\\System32',
        'C:\\Windows\\SysWOW64',
    ];

    private array $limitesRequisição = [];
    private int $maxRequisitionPorMinuto = 100;
    private int $maxTamanhoArquivo = 104857600; // 100MB

    public function __construct()
    {
        $this->inicializarPermissõesPadrão();
    }

    /**
     * Inicializa permissões padrão
     */
    private function inicializarPermissõesPadrão(): void
    {
        $this->permissões = [
            'leitura' => true,
            'escrita' => true,
            'deleção' => true,
            'execução' => false,
            'git_push' => true,
            'git_pull' => true,
            'git_force_push' => false,
            'upload' => true,
            'ia_chat' => true,
        ];
    }

    /**
     * Valida se um arquivo é seguro para leitura
     */
    public function validarLeituraArquivo(string $caminho): array
    {
        $erros = [];

        // 1. Verificar se arquivo existe
        if (!file_exists($caminho)) {
            $erros[] = 'Arquivo não encontrado';
        }

        // 2. Verificar se está em caminho bloqueado
        if ($this->estáEmCaminhoBlockeado($caminho)) {
            $erros[] = 'Acesso negado: caminho em lista de bloqueio';
        }

        // 3. Verificar extensão perigosa
        $extensão = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        if (in_array($extensão, $this->extensõesInseguras)) {
            $erros[] = "Acesso negado: tipo de arquivo não permitido ($extensão)";
        }

        // 4. Validar permissões
        if (!$this->permissões['leitura']) {
            $erros[] = 'Permissão de leitura negada';
        }

        return [
            'válido' => empty($erros),
            'erros' => $erros,
        ];
    }

    /**
     * Valida se um arquivo é seguro para escrita
     */
    public function validarEscritaArquivo(string $caminho, int $tamanhoNovo = 0): array
    {
        $erros = [];

        // 1. Verificar permissão
        if (!$this->permissões['escrita']) {
            $erros[] = 'Permissão de escrita negada';
        }

        // 2. Verificar se está em caminho bloqueado
        if ($this->estáEmCaminhoBlockeado($caminho)) {
            $erros[] = 'Acesso negado: caminho em lista de bloqueio';
        }

        // 3. Verificar tamanho
        if ($tamanhoNovo > $this->maxTamanhoArquivo) {
            $erros[] = "Arquivo muito grande (max: " . ($this->maxTamanhoArquivo / 1048576) . "MB)";
        }

        // 4. Validar extensão (se for nova criação)
        if (!file_exists($caminho)) {
            $extensão = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
            if (in_array($extensão, ['php', 'phtml', 'phar', 'phps'])) {
                // Permitir mas com aviso
                return [
                    'válido' => empty($erros),
                    'erros' => $erros,
                    'aviso' => 'Arquivo PHP será criado. Certifique-se que é seguro.',
                ];
            }
        }

        return [
            'válido' => empty($erros),
            'erros' => $erros,
        ];
    }

    /**
     * Valida comando Git antes de executar
     */
    public function validarComandoGit(string $comando): array
    {
        $erros = [];
        $avisos = [];

        $comandoLower = strtolower($comando);

        // Bloquear force push
        if (preg_match('/force|--force|-f\b/', $comandoLower)) {
            if (!$this->permissões['git_force_push']) {
                $erros[] = 'Force push não é permitido';
            } else {
                $avisos[] = 'Force push detectado - operação irreversível';
            }
        }

        // Avisar sobre resets perigosos
        if (preg_match('/reset.*hard|reset.*--hard/', $comandoLower)) {
            $avisos[] = 'Reset hard detectado - perderá alterações locais';
        }

        // Validar pull/push
        if (!$this->permissões['git_push'] && preg_match('/push/', $comandoLower)) {
            $erros[] = 'Permissão de push negada';
        }

        if (!$this->permissões['git_pull'] && preg_match('/pull|fetch/', $comandoLower)) {
            $erros[] = 'Permissão de pull negada';
        }

        // Detectar injection de comandos
        if (preg_match('/[;&|`$()]/', $comando)) {
            $erros[] = 'Caracteres suspeitos detectados no comando';
        }

        return [
            'válido' => empty($erros),
            'erros' => $erros,
            'avisos' => $avisos,
        ];
    }

    /**
     * Valida conteúdo antes de salvar (detecta código malicioso básico)
     */
    public function validarConteúdo(string $conteúdo, string $tipo = 'php'): array
    {
        $erros = [];
        $avisos = [];

        if ($tipo === 'php') {
            // Detectar funções perigosas
            $funçõesPerigosas = ['eval', 'exec', 'system', 'passthru', 'shell_exec', 'proc_open'];
            
            foreach ($funçõesPerigosas as $funcao) {
                if (preg_match('/\b' . preg_quote($funcao) . '\s*\(/', $conteúdo)) {
                    $avisos[] = "Função potencialmente perigosa detectada: $funcao";
                }
            }

            // Detectar access a variables globais suspeitas
            if (preg_match('/\$_REQUEST|\$_POST|\$_GET.*system|eval/', $conteúdo)) {
                $avisos[] = 'Uso de variáveis globais sem sanitização detectado';
            }
        }

        return [
            'válido' => empty($erros),
            'erros' => $erros,
            'avisos' => $avisos,
        ];
    }

    /**
     * Rate limiting - verifica se usuário excedeu limite de requisições
     */
    public function verificarRateLimit(string $usuarioId): array
    {
        if (!isset($this->limitesRequisição[$usuarioId])) {
            $this->limitesRequisição[$usuarioId] = [
                'contador' => 0,
                'resetar_em' => time() + 60,
            ];
        }

        $limite = &$this->limitesRequisição[$usuarioId];

        // Resetar se passou de 1 minuto
        if (time() >= $limite['resetar_em']) {
            $limite['contador'] = 0;
            $limite['resetar_em'] = time() + 60;
        }

        $limite['contador']++;

        return [
            'permitido' => $limite['contador'] <= $this->maxRequisitionPorMinuto,
            'requisições_atuais' => $limite['contador'],
            'limite' => $this->maxRequisitionPorMinuto,
            'resetar_em' => $limite['resetar_em'],
        ];
    }

    /**
     * Sanitiza caminho de arquivo
     */
    public function sanitizarCaminho(string $caminho): string
    {
        // Normalizar separadores
        $caminho = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $caminho);

        // Remover sequências perigosas
        $caminho = str_replace(['../', '..\\'], '', $caminho);
        $caminho = preg_replace('/\.+/', '.', $caminho);

        return $caminho;
    }

    /**
     * Verifica se caminho está bloqueado
     */
    private function estáEmCaminhoBlockeado(string $caminho): bool
    {
        $caminhoReal = realpath($caminho) ?: $caminho;

        foreach ($this->caminhosBloqueados as $bloqueado) {
            if (strpos($caminhoReal, $bloqueado) === 0 || strpos($caminho, $bloqueado) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Atualiza permissão
     */
    public function definirPermissão(string $chave, bool $valor): void
    {
        if (isset($this->permissões[$chave])) {
            $this->permissões[$chave] = $valor;
        }
    }

    /**
     * Obtém todas as permissões
     */
    public function obterPermissões(): array
    {
        return $this->permissões;
    }

    /**
     * Gera relatório de segurança
     */
    public function gerarRelatório(): array
    {
        return [
            'permissões' => $this->permissões,
            'extensões_bloqueadas' => $this->extensõesInseguras,
            'caminhos_bloqueados' => $this->caminhosBloqueados,
            'max_tamanho_arquivo' => $this->maxTamanhoArquivo,
            'max_requisições_por_minuto' => $this->maxRequisitionPorMinuto,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
