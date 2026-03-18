<h2 class="panel-title">Chat IA</h2>

<div id="chat-history" class="chat-history">
    <?php if (!empty($chatHistory)): ?>
        <?php foreach ($chatHistory as $message): ?>
            <div class="chat-message chat-message-<?= htmlspecialchars($message['role']) ?>">
                <div class="chat-role">
                    <?= $message['role'] === 'user' ? 'Você' : 'IA' ?>
                </div>
                <div class="chat-content">
                    <?= nl2br(htmlspecialchars($message['message'] ?? $message['content'] ?? '')) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-chat">Nenhuma mensagem ainda.</div>
    <?php endif; ?>
</div>

<div class="attachment-box">
    <div class="attachment-title">Anexos</div>

    <form id="attachment-form" class="attachment-form" enctype="multipart/form-data">
        <input type="file" id="attachment-input" name="attachment">
        <input type="hidden" id="attachment-upload-url" value="<?= htmlspecialchars($baseUrl) ?>/attachments/upload">
        <button type="submit" class="chat-button secondary">Enviar anexo</button>
    </form>

    <div id="attachment-status" class="chat-status"></div>

    <div id="attachment-list" class="attachment-list">
        <?php if (!empty($attachments)): ?>
            <?php foreach ($attachments as $attachment): ?>
                <label class="attachment-item">
                        <input
                            type="radio"
                            name="selected_attachment"
                            value="<?= htmlspecialchars($attachment['relative_path'] ?? '') ?>"
                        >
                        <div>
                            <strong><?= htmlspecialchars($attachment['original_name'] ?? $attachment['stored_name'] ?? 'arquivo') ?></strong>
                            <div><?= htmlspecialchars($attachment['extension'] ?? '') ?> • <?= htmlspecialchars((string) ($attachment['size'] ?? 0)) ?> bytes</div>

                            <button
                                type="button"
                                class="attachment-delete"
                                data-path="<?= htmlspecialchars($attachment['relative_path'] ?? '') ?>"
                            >
                                remover
                            </button>
                        </div>
                    </label>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-chat">Nenhum anexo enviado ainda.</div>
        <?php endif; ?>
    </div>
</div>

<form id="chat-form" class="chat-form">
    <textarea
        id="chat-prompt"
        name="prompt"
        class="chat-textarea"
        placeholder="Digite sua pergunta sobre o projeto..."
    ></textarea>

    <input type="hidden" id="chat-file-path" value="<?= htmlspecialchars($filePath ?? '') ?>">
    <input type="hidden" id="chat-current-path" value="<?= htmlspecialchars($currentPath ?? '') ?>">
    <input type="hidden" id="chat-send-url" value="<?= htmlspecialchars($baseUrl) ?>/chat/send">
    <input type="hidden" id="chat-clear-url" value="<?= htmlspecialchars($baseUrl) ?>/chat/clear">
    <input type="hidden" id="chat-apply-url" value="<?= htmlspecialchars($baseUrl) ?>/workspace/apply-ai">
    <input type="hidden" id="chat-undo-url" value="<?= htmlspecialchars($baseUrl) ?>/workspace/undo-ai">

    <div class="chat-actions">
        <button type="submit" class="chat-button">Enviar</button>
        <button type="button" id="chat-apply-btn" class="chat-button">Aplicar alteração da IA</button>
        <button type="button" id="chat-undo-btn" class="chat-button warning">Desfazer última aplicação</button>
        <button type="button" id="chat-clear-btn" class="chat-button secondary">Limpar</button>
    </div>

    <div id="chat-status" class="chat-status"></div>
</form>
