/**
 * Terminal Web - Emulador de Terminal no Navegador
 * Permite executar comandos e ver a saída em tempo real
 */

class WebTerminal {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.history = [];
        this.historyIndex = -1;
        this.currentCommand = '';
        this.init();
    }

    /**
     * Inicializar o terminal
     */
    init() {
        if (!this.container) {
            console.error('Container não encontrado');
            return;
        }

        this.container.innerHTML = `
            <div class="web-terminal">
                <div class="terminal-header">
                    <span class="terminal-title">Terminal</span>
                    <button class="terminal-btn-clear" title="Limpar Terminal">Limpar</button>
                </div>
                <div class="terminal-output" id="terminal-output"></div>
                <div class="terminal-input-wrapper">
                    <span class="terminal-prompt">$</span>
                    <input type="text" class="terminal-input" id="terminal-input" placeholder="Digite um comando...">
                </div>
            </div>
        `;

        this.output = this.container.querySelector('#terminal-output');
        this.input = this.container.querySelector('#terminal-input');
        this.clearBtn = this.container.querySelector('.terminal-btn-clear');

        this.setupEventListeners();
        this.printWelcome();
    }

    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        this.input.addEventListener('keydown', (e) => this.handleKeyDown(e));
        this.clearBtn.addEventListener('click', () => this.clear());
    }

    /**
     * Tratar teclas pressionadas
     */
    handleKeyDown(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const command = this.input.value.trim();
            if (command) {
                this.executeCommand(command);
            }
            this.input.value = '';
            this.currentCommand = '';
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.showPreviousCommand();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.showNextCommand();
        }
    }

    /**
     * Executar um comando
     */
    async executeCommand(command) {
        // Adicionar ao histórico
        this.history.push(command);
        this.historyIndex = this.history.length;

        // Exibir o comando executado
        this.printLine(`$ ${command}`, 'command');

        try {
            const response = await fetch('/workspace/terminal/execute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ command }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.printLine(result.output, 'output');
            } else {
                this.printLine(result.output, 'error');
            }
        } catch (error) {
            this.printLine(`Erro ao executar comando: ${error.message}`, 'error');
        }
    }

    /**
     * Mostrar o comando anterior
     */
    showPreviousCommand() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            this.input.value = this.history[this.historyIndex];
            this.currentCommand = this.history[this.historyIndex];
        }
    }

    /**
     * Mostrar o próximo comando
     */
    showNextCommand() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.input.value = this.history[this.historyIndex];
            this.currentCommand = this.history[this.historyIndex];
        } else {
            this.historyIndex = this.history.length;
            this.input.value = '';
            this.currentCommand = '';
        }
    }

    /**
     * Imprimir uma linha no terminal
     */
    printLine(text, className = '') {
        const line = document.createElement('div');
        line.className = `terminal-line ${className}`;
        line.textContent = text;
        this.output.appendChild(line);
        this.output.scrollTop = this.output.scrollHeight;
    }

    /**
     * Imprimir mensagem de boas-vindas
     */
    printWelcome() {
        this.printLine('Frameworkia Terminal v1.0', 'welcome');
        this.printLine('Digite "help" para ver os comandos disponíveis.', 'welcome');
        this.printLine('', '');
    }

    /**
     * Limpar o terminal
     */
    clear() {
        this.output.innerHTML = '';
        this.printWelcome();
    }

    /**
     * Obter o histórico
     */
    getHistory() {
        return this.history;
    }
}

// Exportar para uso global
window.WebTerminal = WebTerminal;
