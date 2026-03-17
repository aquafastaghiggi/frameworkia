<div class="editor-header">
    <div class="editor-tab">
        <?= htmlspecialchars($filePath !== '' ? $filePath : 'Nenhum arquivo aberto') ?>
    </div>

    <?php if (!empty($filePath)): ?>
        <div class="editor-actions">
            <button id="save-file-btn" class="save-button">Salvar</button>

            <?php if (!empty($gitData['enabled'])): ?>
                <button
                    id="stage-file-btn"
                    class="git-button"
                    data-path="<?= htmlspecialchars($filePath) ?>"
                    data-stage-url="<?= htmlspecialchars($baseUrl) ?>/workspace/git/stage"
                >
                    Stage
                </button>
            <?php endif; ?>

            <span id="save-status" class="save-status"></span>
        </div>
    <?php endif; ?>
</div>

<div class="editor-content">
    <?php if (!empty($fileError)): ?>
        <div class="error-box"><?= htmlspecialchars($fileError) ?></div>

    <?php elseif (!empty($filePath)): ?>

        <div
            id="editor"
            data-content="<?= htmlspecialchars($fileContent ?? '') ?>"
            data-filename="<?= htmlspecialchars($filePath ?? '') ?>"
            data-save-url="<?= htmlspecialchars($baseUrl) ?>/workspace/save"
            style="width: 100%; height: 100%;"
        ></div>

    <?php else: ?>
        <div class="empty-state">
            <h3>Bem-vindo ao workspace</h3>
            <p>Selecione um arquivo no Explorer para visualizar ou editar o conteúdo.</p>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($filePath) && !empty($gitData['enabled'])): ?>
    <div class="diff-panel">
        <div class="diff-header">Git diff do arquivo atual</div>

        <?php if (!empty($gitDiffError)): ?>
            <div class="error-box"><?= htmlspecialchars($gitDiffError) ?></div>
        <?php elseif ($gitDiff !== null && trim($gitDiff) !== ''): ?>
            <pre class="diff-content"><?= htmlspecialchars($gitDiff) ?></pre>
        <?php else: ?>
            <div class="diff-empty">Sem diff para este arquivo.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>