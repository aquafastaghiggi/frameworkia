document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chat-form');
    const prompt = document.getElementById('chat-prompt');
    const history = document.getElementById('chat-history');
    const status = document.getElementById('chat-status');
    const clearButton = document.getElementById('chat-clear-btn');
    const applyButton = document.getElementById('chat-apply-btn');
    const undoButton = document.getElementById('chat-undo-btn');
    const sendUrlInput = document.getElementById('chat-send-url');
    const clearUrlInput = document.getElementById('chat-clear-url');
    const applyUrlInput = document.getElementById('chat-apply-url');
    const undoUrlInput = document.getElementById('chat-undo-url');
    const filePathInput = document.getElementById('chat-file-path');
    const currentPathInput = document.getElementById('chat-current-path');

    const attachmentForm = document.getElementById('attachment-form');
    const attachmentInput = document.getElementById('attachment-input');
    const attachmentUploadUrl = document.getElementById('attachment-upload-url');
    const attachmentStatus = document.getElementById('attachment-status');
    const attachmentList = document.getElementById('attachment-list');

    if (!form || !prompt || !history || !sendUrlInput || !clearUrlInput) {
        return;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const sendUrl = sendUrlInput.value;
        const text = prompt.value.trim();
        const filePath = filePathInput ? filePathInput.value : '';
        const currentPath = currentPathInput ? currentPathInput.value : '';
        const selectedAttachment = document.querySelector('input[name="selected_attachment"]:checked');
        const attachmentPath = selectedAttachment ? selectedAttachment.value : '';

        if (!text) {
            if (status) status.textContent = 'Digite uma mensagem.';
            return;
        }

        if (status) status.textContent = 'Enviando para IA...';

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    prompt: text,
                    file_path: filePath,
                    current_path: currentPath,
                    attachment_path: attachmentPath
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Erro ao enviar mensagem.');
            }

            renderHistory(history, data.history);
            prompt.value = '';

            if (status) status.textContent = 'Resposta recebida.';
        } catch (error) {
            if (status) status.textContent = 'Erro no chat.';
            alert(error.message || 'Falha ao enviar mensagem.');
        }
    });

    if (attachmentForm) {
        attachmentForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!attachmentInput || !attachmentInput.files || attachmentInput.files.length === 0) {
                if (attachmentStatus) attachmentStatus.textContent = 'Selecione um arquivo primeiro.';
                return;
            }

            const uploadUrl = attachmentUploadUrl ? attachmentUploadUrl.value : '';

            if (!uploadUrl) {
                if (attachmentStatus) attachmentStatus.textContent = 'URL de upload não encontrada.';
                return;
            }

            const formData = new FormData();
            formData.append('attachment', attachmentInput.files[0]);

            if (attachmentStatus) attachmentStatus.textContent = 'Enviando anexo...';

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao enviar anexo.');
                }

                if (attachmentStatus) attachmentStatus.textContent = 'Anexo enviado com sucesso.';
                attachmentInput.value = '';
                renderAttachments(attachmentList, data.attachments || []);
            } catch (error) {
                if (attachmentStatus) attachmentStatus.textContent = 'Erro no upload.';
                alert(error.message || 'Falha ao enviar anexo.');
            }
        });
    }

    if (applyButton) {
        applyButton.addEventListener('click', async function () {
            const applyUrl = applyUrlInput ? applyUrlInput.value : '';
            const filePath = filePathInput ? filePathInput.value : '';

            if (!filePath) {
                alert('Abra um arquivo antes de aplicar a resposta da IA.');
                return;
            }

            if (!applyUrl) {
                alert('URL de aplicação não encontrada.');
                return;
            }

            const confirmed = window.confirm(
                'A IDE tentará aplicar a última resposta da IA no arquivo atual. Primeiro será feita substituição parcial quando possível. Um backup será criado. Deseja continuar?'
            );

            if (!confirmed) {
                return;
            }

            if (status) status.textContent = 'Aplicando sugestão da IA...';

            try {
                const response = await fetch(applyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        file_path: filePath
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao aplicar sugestão.');
                }

                if (status) status.textContent = 'Sugestão aplicada com sucesso.';
                window.location.reload();
            } catch (error) {
                if (status) status.textContent = 'Erro ao aplicar sugestão.';
                alert(error.message || 'Falha ao aplicar resposta da IA.');
            }
        });
    }

    if (undoButton) {
        undoButton.addEventListener('click', async function () {
            const undoUrl = undoUrlInput ? undoUrlInput.value : '';
            const filePath = filePathInput ? filePathInput.value : '';

            if (!filePath) {
                alert('Abra o arquivo que deseja restaurar.');
                return;
            }

            if (!undoUrl) {
                alert('URL de restauração não encontrada.');
                return;
            }

            const confirmed = window.confirm(
                'Deseja restaurar o backup da última aplicação da IA neste arquivo?'
            );

            if (!confirmed) {
                return;
            }

            if (status) status.textContent = 'Restaurando backup...';

            try {
                const response = await fetch(undoUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        file_path: filePath
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao restaurar backup.');
                }

                if (status) status.textContent = 'Backup restaurado com sucesso.';
                window.location.reload();
            } catch (error) {
                if (status) status.textContent = 'Erro ao restaurar backup.';
                alert(error.message || 'Falha ao restaurar backup.');
            }
        });
    }

    clearButton.addEventListener('click', async function () {
        const clearUrl = clearUrlInput.value;

        if (status) status.textContent = 'Limpando histórico...';

        try {
            const response = await fetch(clearUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Erro ao limpar histórico.');
            }

            renderHistory(history, []);
            if (status) status.textContent = 'Histórico limpo.';
        } catch (error) {
            if (status) status.textContent = 'Erro ao limpar histórico.';
            alert(error.message || 'Falha ao limpar histórico.');
        }
    });
});

function renderHistory(container, messages) {
    if (!container) return;

    if (!messages || messages.length === 0) {
        container.innerHTML = '<div class="empty-chat">Nenhuma mensagem ainda.</div>';
        return;
    }

    container.innerHTML = messages.map(function (message) {
        const role = message.role === 'user' ? 'Você' : 'IA';
        const cssClass = message.role === 'user' ? 'user' : 'assistant';
        const content = escapeHtml(message.content).replace(/\n/g, '<br>');

        return `
            <div class="chat-message chat-message-${cssClass}">
                <div class="chat-role">${role}</div>
                <div class="chat-content">${content}</div>
            </div>
        `;
    }).join('');

    container.scrollTop = container.scrollHeight;
}

function renderAttachments(container, attachments) {
    if (!container) return;

    if (!attachments || attachments.length === 0) {
        container.innerHTML = '<div class="empty-chat">Nenhum anexo enviado ainda.</div>';
        return;
    }

    container.innerHTML = attachments.map(function (attachment) {
        const name = escapeHtml(attachment.original_name || attachment.stored_name || 'arquivo');
        const ext = escapeHtml(attachment.extension || '');
        const size = escapeHtml(String(attachment.size || 0));

        return `
            <div class="attachment-item">
                <strong>${name}</strong>
                <div>${ext} • ${size} bytes</div>
            </div>
        `;
    }).join('');
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };

    return String(text).replace(/[&<>"']/g, function (m) {
        return map[m];
    });

    document.addEventListener('click', async function (event) {
    if (!event.target.classList.contains('attachment-delete')) {
        return;
    }

    const path = event.target.getAttribute('data-path');
    const uploadUrlInput = document.getElementById('attachment-upload-url');

    if (!uploadUrlInput) {
        alert('URL de upload não encontrada.');
        return;
    }

    const deleteUrl = uploadUrlInput.value.replace('/upload', '/delete');

    if (!path) return;

    const confirmed = confirm('Remover este anexo?');

    if (!confirmed) return;

    try {
        const response = await fetch(deleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ path })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Erro ao remover anexo.');
        }

        renderAttachments(document.getElementById('attachment-list'), data.attachments || []);
    } catch (e) {
        alert('Erro ao remover anexo: ' + (e.message || 'falha desconhecida'));
    }
});
}