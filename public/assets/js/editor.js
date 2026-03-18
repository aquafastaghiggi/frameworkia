require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });

require(['vs/editor/editor.main'], function () {
    const container = document.getElementById('editor');
    const saveButton = document.getElementById('save-file-btn');
    const stageButton = document.getElementById('stage-file-btn');
    const commitButton = document.getElementById('commit-btn');
    const commitMessage = document.getElementById('commit-message');
    const saveStatus = document.getElementById('save-status');
    const commitStatus = document.getElementById('commit-status');

    if (container) {
        const initialContent = container.dataset.content || '';
        const filename = container.dataset.filename || '';
        const saveUrl = container.dataset.saveUrl || '';

        window.editor = monaco.editor.create(container, {
            value: initialContent,
            language: detectLanguage(filename),
            theme: 'vs-dark',
            automaticLayout: true,
            fontSize: 14,
            minimap: {
                enabled: true
            },
            scrollBeyondLastLine: false,
            renderWhitespace: 'selection',
            tabSize: 4,
            insertSpaces: true
        });

        // Atalho Ctrl+S para salvar
        window.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
            if (saveButton && !saveButton.disabled) {
                saveButton.click();
            }
        });

        if (saveButton && saveUrl && filename) {
            saveButton.addEventListener('click', async function () {
                saveButton.disabled = true;

                if (saveStatus) {
                    saveStatus.textContent = 'Salvando...';
                }

                try {
                    const response = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            path: filename,
                            content: window.editor.getValue()
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Erro ao salvar arquivo.');
                    }

                    if (saveStatus) {
                        saveStatus.textContent = 'Salvo com sucesso.';
                    }
                } catch (error) {
                    if (saveStatus) {
                        saveStatus.textContent = 'Erro ao salvar.';
                    }

                    alert(error.message || 'Falha ao salvar arquivo.');
                } finally {
                    saveButton.disabled = false;
                }
            });
        }
    }

    if (stageButton) {
        stageButton.addEventListener('click', async function () {
            const stageUrl = stageButton.dataset.stageUrl || '';
            const path = stageButton.dataset.path || '';

            if (!stageUrl || !path) {
                alert('Dados insuficientes para stage.');
                return;
            }

            stageButton.disabled = true;

            try {
                const response = await fetch(stageUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ path })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao adicionar arquivo ao stage.');
                }

                alert('Arquivo adicionado ao stage com sucesso.');
                window.location.reload();
            } catch (error) {
                alert(error.message || 'Falha ao adicionar ao stage.');
            } finally {
                stageButton.disabled = false;
            }
        });
    }

    if (commitButton) {
        commitButton.addEventListener('click', async function () {
            const commitUrl = commitButton.dataset.commitUrl || '';
            const message = commitMessage ? commitMessage.value.trim() : '';

            if (!commitUrl) {
                alert('URL de commit não encontrada.');
                return;
            }

            if (!message) {
                alert('Informe uma mensagem de commit.');
                return;
            }

            commitButton.disabled = true;

            if (commitStatus) {
                commitStatus.textContent = 'Realizando commit...';
            }

            try {
                const response = await fetch(commitUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao realizar commit.');
                }

                if (commitStatus) {
                    commitStatus.textContent = 'Commit realizado com sucesso.';
                }

                setTimeout(() => {
                    window.location.reload();
                }, 700);
            } catch (error) {
                if (commitStatus) {
                    commitStatus.textContent = 'Erro no commit.';
                }

                alert(error.message || 'Falha ao realizar commit.');
            } finally {
                commitButton.disabled = false;
            }
        });
    }
});

function detectLanguage(filename) {
    if (!filename) return 'plaintext';

    if (filename.endsWith('.php')) return 'php';
    if (filename.endsWith('.js')) return 'javascript';
    if (filename.endsWith('.json')) return 'json';
    if (filename.endsWith('.html')) return 'html';
    if (filename.endsWith('.css')) return 'css';
    if (filename.endsWith('.md')) return 'markdown';

    return 'plaintext';
}