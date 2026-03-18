<?php

declare(strict_types=1);

namespace App\UI;

/**
 * Gerenciador de Split Editor
 * Permite dividir a tela de edição em múltiplos painéis
 */
class SplitEditorManager
{
    /**
     * @var array<string, array{file: string, content: string, position: int}>
     */
    private array $panels = [];

    /**
     * @var string
     */
    private string $layout = 'single'; // 'single', 'horizontal', 'vertical'

    /**
     * Adicionar um painel de edição
     */
    public function addPanel(string $panelId, string $filePath, string $content): void
    {
        $this->panels[$panelId] = [
            'file' => $filePath,
            'content' => $content,
            'position' => count($this->panels),
        ];
    }

    /**
     * Remover um painel de edição
     */
    public function removePanel(string $panelId): void
    {
        unset($this->panels[$panelId]);
    }

    /**
     * Atualizar o conteúdo de um painel
     */
    public function updatePanel(string $panelId, string $content): void
    {
        if (isset($this->panels[$panelId])) {
            $this->panels[$panelId]['content'] = $content;
        }
    }

    /**
     * Obter um painel específico
     */
    public function getPanel(string $panelId): ?array
    {
        return $this->panels[$panelId] ?? null;
    }

    /**
     * Obter todos os painéis
     */
    public function getPanels(): array
    {
        return $this->panels;
    }

    /**
     * Definir o layout (single, horizontal, vertical)
     */
    public function setLayout(string $layout): void
    {
        if (in_array($layout, ['single', 'horizontal', 'vertical'])) {
            $this->layout = $layout;
        }
    }

    /**
     * Obter o layout atual
     */
    public function getLayout(): string
    {
        return $this->layout;
    }

    /**
     * Exportar estado do split editor para JSON
     */
    public function toArray(): array
    {
        return [
            'layout' => $this->layout,
            'panels' => $this->panels,
            'count' => count($this->panels),
        ];
    }
}
