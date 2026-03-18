/**
 * Split Editor - Gerenciador de Editor Dividido
 * Permite dividir a tela de edição em múltiplos painéis
 */

class SplitEditor {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.panels = new Map();
        this.layout = 'single';
        this.activePanel = null;
        this.init();
    }

    /**
     * Inicializar o split editor
     */
    init() {
        if (!this.container) {
            console.error('Container não encontrado');
            return;
        }

        this.container.innerHTML = `
            <div class="split-editor-wrapper">
                <div class="split-editor-toolbar">
                    <button class="split-btn" data-layout="single" title="Painel Único">
                        <i class="icon-single"></i>
                    </button>
                    <button class="split-btn" data-layout="horizontal" title="Divisão Horizontal">
                        <i class="icon-horizontal"></i>
                    </button>
                    <button class="split-btn" data-layout="vertical" title="Divisão Vertical">
                        <i class="icon-vertical"></i>
                    </button>
                    <button class="split-btn" id="close-panel-btn" title="Fechar Painel">
                        <i class="icon-close"></i>
                    </button>
                </div>
                <div class="split-editor-container" id="split-container"></div>
            </div>
        `;

        this.editorContainer = this.container.querySelector('#split-container');
        this.setupEventListeners();
    }

    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Layout buttons
        this.container.querySelectorAll('.split-btn[data-layout]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const layout = e.currentTarget.dataset.layout;
                this.setLayout(layout);
            });
        });

        // Close panel button
        const closeBtn = this.container.querySelector('#close-panel-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeActivePanel());
        }
    }

    /**
     * Adicionar um painel de edição
     */
    addPanel(panelId, filePath, content = '') {
        const panel = {
            id: panelId,
            file: filePath,
            content: content,
            isDirty: false,
        };

        this.panels.set(panelId, panel);
        this.renderPanels();
        return panel;
    }

    /**
     * Remover um painel
     */
    removePanel(panelId) {
        this.panels.delete(panelId);
        if (this.activePanel === panelId) {
            this.activePanel = null;
        }
        this.renderPanels();
    }

    /**
     * Definir o layout
     */
    setLayout(layout) {
        if (['single', 'horizontal', 'vertical'].includes(layout)) {
            this.layout = layout;
            this.renderPanels();
        }
    }

    /**
     * Renderizar os painéis
     */
    renderPanels() {
        this.editorContainer.innerHTML = '';
        this.editorContainer.className = `split-editor-${this.layout}`;

        if (this.panels.size === 0) {
            this.editorContainer.innerHTML = '<div class="empty-editor">Nenhum arquivo aberto</div>';
            return;
        }

        const panelsArray = Array.from(this.panels.values());

        // Modo single: mostrar apenas o primeiro painel
        if (this.layout === 'single') {
            const panel = panelsArray[0];
            this.renderPanel(panel);
        } else if (this.layout === 'horizontal') {
            // Modo horizontal: dividir em colunas
            panelsArray.forEach((panel, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'split-panel-wrapper horizontal';
                wrapper.style.flex = `1 1 ${100 / panelsArray.length}%`;
                wrapper.innerHTML = this.getPanelHTML(panel);
                this.editorContainer.appendChild(wrapper);
            });
        } else if (this.layout === 'vertical') {
            // Modo vertical: dividir em linhas
            panelsArray.forEach((panel, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'split-panel-wrapper vertical';
                wrapper.style.flex = `1 1 ${100 / panelsArray.length}%`;
                wrapper.innerHTML = this.getPanelHTML(panel);
                this.editorContainer.appendChild(wrapper);
            });
        }

        // Adicionar event listeners aos painéis
        this.editorContainer.querySelectorAll('.split-panel').forEach(panelEl => {
            panelEl.addEventListener('click', (e) => {
                this.activePanel = panelEl.dataset.panelId;
            });
        });
    }

    /**
     * Renderizar um painel individual
     */
    renderPanel(panel) {
        const wrapper = document.createElement('div');
        wrapper.className = 'split-panel-wrapper single';
        wrapper.innerHTML = this.getPanelHTML(panel);
        this.editorContainer.appendChild(wrapper);
    }

    /**
     * Obter HTML de um painel
     */
    getPanelHTML(panel) {
        return `
            <div class="split-panel" data-panel-id="${panel.id}">
                <div class="panel-header">
                    <span class="panel-title">${this.escapeHtml(panel.file)}</span>
                    <span class="panel-dirty ${panel.isDirty ? 'show' : ''}">●</span>
                </div>
                <textarea class="panel-editor" data-panel-id="${panel.id}">${this.escapeHtml(panel.content)}</textarea>
            </div>
        `;
    }

    /**
     * Obter o conteúdo de um painel
     */
    getPanelContent(panelId) {
        const panel = this.panels.get(panelId);
        if (panel) {
            const textarea = this.editorContainer.querySelector(`textarea[data-panel-id="${panelId}"]`);
            if (textarea) {
                return textarea.value;
            }
        }
        return null;
    }

    /**
     * Atualizar o conteúdo de um painel
     */
    updatePanelContent(panelId, content) {
        const panel = this.panels.get(panelId);
        if (panel) {
            panel.content = content;
            panel.isDirty = true;
            const textarea = this.editorContainer.querySelector(`textarea[data-panel-id="${panelId}"]`);
            if (textarea) {
                textarea.value = content;
            }
        }
    }

    /**
     * Fechar o painel ativo
     */
    closeActivePanel() {
        if (this.activePanel) {
            this.removePanel(this.activePanel);
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Exportar estado
     */
    getState() {
        return {
            layout: this.layout,
            panels: Array.from(this.panels.values()),
            activePanel: this.activePanel,
        };
    }
}

// Exportar para uso global
window.SplitEditor = SplitEditor;
