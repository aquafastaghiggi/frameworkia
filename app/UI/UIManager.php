<?php

declare(strict_types=1);

namespace App\UI;

/**
 * Gerenciador de Interface de Usuário Profissional
 * 
 * Responsável por:
 * - Gerenciamento de abas (tabs)
 * - Suporte a split editor (dividir editor)
 * - Estados de carregamento
 * - Notificações em tempo real
 * - Layout responsivo VSCode-like
 */
class UIManager
{
    private array $abas = [];
    private array $notificações = [];
    private string $abaAtiva = '';
    private array $layouts = [];
    private int $notificaçãoMaxId = 0;

    public function __construct()
    {
        // Recuperar estado da sessão se existir
        $this->carregar();
    }

    /**
     * Abre um arquivo em uma nova aba
     */
    public function abrirAba(string $caminhoArquivo, string $conteúdo = '', array $metadata = []): array
    {
        $id = 'aba_' . uniqid();
        
        $this->abas[$id] = [
            'id' => $id,
            'caminho' => $caminhoArquivo,
            'nome' => basename($caminhoArquivo),
            'extensão' => pathinfo($caminhoArquivo, PATHINFO_EXTENSION),
            'conteúdo' => $conteúdo,
            'modificado' => false,
            'salvo' => true,
            'metadata' => $metadata,
            'criada_em' => date('Y-m-d H:i:s'),
        ];

        $this->abaAtiva = $id;
        $this->salvar();

        return $this->abas[$id];
    }

    /**
     * Fecha uma aba
     */
    public function fecharAba(string $idAba): bool
    {
        if (!isset($this->abas[$idAba])) {
            return false;
        }

        unset($this->abas[$idAba]);

        // Se era a aba ativa, ativar outra
        if ($this->abaAtiva === $idAba) {
            $this->abaAtiva = !empty($this->abas) ? array_key_first($this->abas) : '';
        }

        $this->salvar();
        return true;
    }

    /**
     * Obtém todas as abas abertas
     */
    public function obterAbas(): array
    {
        return array_values($this->abas);
    }

    /**
     * Obtém aba específica
     */
    public function obterAba(string $idAba): ?array
    {
        return $this->abas[$idAba] ?? null;
    }

    /**
     * Ativa uma aba
     */
    public function ativarAba(string $idAba): bool
    {
        if (!isset($this->abas[$idAba])) {
            return false;
        }

        $this->abaAtiva = $idAba;
        $this->salvar();
        return true;
    }

    /**
     * Marca aba como modificada
     */
    public function marcarModificado(string $idAba, bool $modificado = true): bool
    {
        if (!isset($this->abas[$idAba])) {
            return false;
        }

        $this->abas[$idAba]['modificado'] = $modificado;
        $this->abas[$idAba]['salvo'] = !$modificado;
        $this->salvar();
        return true;
    }

    /**
     * Cria um layout com split editor
     */
    public function criarLayout(string $tipo = 'dois-colunas'): array
    {
        $idLayout = 'layout_' . uniqid();

        $this->layouts[$idLayout] = [
            'id' => $idLayout,
            'tipo' => $tipo, // 'dois-colunas', 'dois-linhas', 'três-painéis', etc
            'painéis' => [],
            'ativo' => true,
            'criado_em' => date('Y-m-d H:i:s'),
        ];

        return $this->layouts[$idLayout];
    }

    /**
     * Adiciona aba a um painel do layout
     */
    public function adicionarAbaPainel(string $idLayout, int $numeroPainel, string $idAba): bool
    {
        if (!isset($this->layouts[$idLayout]) || !isset($this->abas[$idAba])) {
            return false;
        }

        if (!isset($this->layouts[$idLayout]['painéis'][$numeroPainel])) {
            $this->layouts[$idLayout]['painéis'][$numeroPainel] = [];
        }

        $this->layouts[$idLayout]['painéis'][$numeroPainel][] = $idAba;
        $this->salvar();
        return true;
    }

    /**
     * Obtém layouts disponíveis
     */
    public function obterLayouts(): array
    {
        return array_values($this->layouts);
    }

    /**
     * Adiciona notificação
     */
    public function adicionarNotificação(
        string $tipo,
        string $mensagem,
        string $titulo = '',
        int $duração = 5000
    ): array {
        $this->notificaçãoMaxId++;
        
        $notificação = [
            'id' => 'notif_' . $this->notificaçãoMaxId,
            'tipo' => $tipo, // 'sucesso', 'erro', 'aviso', 'informação'
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'duração' => $duração,
            'criada_em' => date('Y-m-d H:i:s'),
            'lida' => false,
        ];

        $this->notificações[] = $notificação;

        // Limitar a 50 notificações
        if (count($this->notificações) > 50) {
            array_shift($this->notificações);
        }

        $this->salvar();
        return $notificação;
    }

    /**
     * Obtém notificações não lidas
     */
    public function obterNotificaçõesNãoLidas(): array
    {
        return array_filter($this->notificações, function($n) {
            return !$n['lida'];
        });
    }

    /**
     * Marca notificação como lida
     */
    public function marcarNotificaçãoComoLida(string $idNotificação): bool
    {
        foreach ($this->notificações as &$notif) {
            if ($notif['id'] === $idNotificação) {
                $notif['lida'] = true;
                $this->salvar();
                return true;
            }
        }
        return false;
    }

    /**
     * Limpa notificações antigas
     */
    public function limparNotificações(): void
    {
        $this->notificações = [];
        $this->salvar();
    }

    /**
     * Define estado de carregamento global
     */
    public function setCarregando(bool $ativo, string $mensagem = ''): array
    {
        return [
            'carregando' => $ativo,
            'mensagem' => $mensagem,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém estado da UI
     */
    public function obterEstado(): array
    {
        return [
            'abas' => array_values($this->abas),
            'aba_ativa' => $this->abaAtiva,
            'layouts' => array_values($this->layouts),
            'notificações' => $this->notificações,
            'notificações_não_lidas' => count($this->obterNotificaçõesNãoLidas()),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Salva estado em sessão
     */
    private function salvar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ui_manager'] = [
                'abas' => $this->abas,
                'aba_ativa' => $this->abaAtiva,
                'layouts' => $this->layouts,
                'notificações' => $this->notificações,
                'notificacao_max_id' => $this->notificaçãoMaxId,
            ];
        }
    }

    /**
     * Carrega estado da sessão
     */
    private function carregar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['ui_manager'])) {
            $data = $_SESSION['ui_manager'];
            $this->abas = $data['abas'] ?? [];
            $this->abaAtiva = $data['aba_ativa'] ?? '';
            $this->layouts = $data['layouts'] ?? [];
            $this->notificações = $data['notificações'] ?? [];
            $this->notificaçãoMaxId = $data['notificacao_max_id'] ?? 0;
        }
    }
}
