<h2 class="panel-title">Painel</h2>

<div class="info-block">
    <strong>Pasta atual</strong>
    <div><?= htmlspecialchars($currentPath !== '' ? $currentPath : '/') ?></div>
</div>

<div class="info-block">
    <strong>Arquivo aberto</strong>
    <div><?= htmlspecialchars($filePath !== '' ? $filePath : 'Nenhum') ?></div>
</div>

<div class="info-block">
    <strong>Status</strong>
    <div>Workspace ativo</div>
</div>

<?php if (!empty($gitData['enabled'])): ?>
    <div class="info-block">
        <strong>Git branch</strong>
        <div><?= htmlspecialchars((string) ($gitData['branch'] ?? '')) ?></div>
    </div>

    <div class="info-block">
        <strong>Commit</strong>

        <textarea
            id="commit-message"
            class="commit-message"
            placeholder="Mensagem do commit"
        ></textarea>

        <button
            id="commit-btn"
            class="git-button commit-button"
            data-commit-url="<?= htmlspecialchars($baseUrl) ?>/workspace/git/commit"
        >
            Commit
        </button>

        <div id="commit-status" class="commit-status"></div>
    </div>

    <div class="info-block">
        <strong>Git status</strong>
        <?php if (!empty($gitData['status'])): ?>
            <ul class="git-list">
                <?php foreach ($gitData['status'] as $line): ?>
                    <li><?= htmlspecialchars($line) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div>Sem alterações</div>
        <?php endif; ?>
    </div>

    <div class="info-block">
        <strong>Últimos commits</strong>
        <?php if (!empty($gitData['commits'])): ?>
            <ul class="git-list">
                <?php foreach ($gitData['commits'] as $commit): ?>
                    <li><?= htmlspecialchars($commit) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div>Nenhum commit encontrado</div>
        <?php endif; ?>
    </div>
<?php elseif (!empty($gitData['error'])): ?>
    <div class="info-block">
        <strong>Git</strong>
        <div><?= htmlspecialchars($gitData['error']) ?></div>
    </div>
<?php else: ?>
    <div class="info-block">
        <strong>Git</strong>
        <div>Workspace não é um repositório Git.</div>
    </div>
<?php endif; ?>

<div class="info-block">
    <strong>Próximos módulos</strong>
    <div>Chat IA, contexto, comandos, diffs melhores</div>
</div>