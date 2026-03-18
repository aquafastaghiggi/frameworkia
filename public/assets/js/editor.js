document.addEventListener('DOMContentLoaded', () => {
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });

    require(['vs/editor/editor.main'], function () {
        const splitEditor = new SplitEditor('split-editor-container');
        const webTerminal = new WebTerminal('terminal-container');

        function createMonacoEditor(element, filename, content) {
            return monaco.editor.create(element, {
                value: content,
                language: detectLanguage(filename),
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                minimap: { enabled: true },
                scrollBeyondLastLine: false,
                renderWhitespace: 'selection',
                tabSize: 4,
                insertSpaces: true
            });
        }

        // Override do método renderPanels do SplitEditor para integrar o Monaco
        splitEditor.renderPanels = function() {
            this.editorContainer.innerHTML = '';
            this.editorContainer.className = `split-editor-${this.layout}`;

            if (this.panels.size === 0) {
                this.editorContainer.innerHTML = '<div class="empty-editor">Nenhum arquivo aberto</div>';
                return;
            }

            const panelsArray = Array.from(this.panels.values());

            const render = (panel) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'split-panel-wrapper ';
                wrapper.innerHTML = this.getPanelHTML(panel);
                this.editorContainer.appendChild(wrapper);

                const editorElement = wrapper.querySelector(`.panel-editor[data-panel-id="${panel.id}"]`);
                if (editorElement) {
                    const editorInstance = createMonacoEditor(editorElement, panel.file, panel.content);
                    panel.editorInstance = editorInstance;
                }
            };

            if (this.layout === 'single') {
                const panel = this.activePanel ? this.panels.get(this.activePanel) : panelsArray[0];
                if(panel) render(panel);
            } else {
                this.editorContainer.style.flexDirection = this.layout === 'horizontal' ? 'row' : 'column';
                panelsArray.forEach(render);
            }
        };

        // Carregar arquivo inicial
        const initialFilePath = document.getElementById('initial-file-path')?.value;
        const initialFileContent = document.getElementById('initial-file-content')?.value;

        if (initialFilePath) {
            splitEditor.addPanel(initialFilePath, initialFilePath, initialFileContent);
            splitEditor.activePanel = initialFilePath;
            splitEditor.renderPanels();
        }
    });
});

function detectLanguage(filename) {
    if (!filename) return 'plaintext';
    const extension = filename.split('.').pop();
    switch (extension) {
        case 'php': return 'php';
        case 'js': return 'javascript';
        case 'json': return 'json';
        case 'html': return 'html';
        case 'css': return 'css';
        case 'md': return 'markdown';
        default: return 'plaintext';
    }
}
