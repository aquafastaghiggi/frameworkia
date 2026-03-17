<h2 class="panel-title">Explorer</h2>

<form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/workspace/open" class="workspace-form">
    <label for="root_path">Pasta do projeto</label>
    <input
        type="text"
        id="root_path"
        name="root_path"
        value="<?= htmlspecialchars($rootPath ?? '') ?>"
        placeholder="C:\xampp\htdocs\meu-projeto"
    >
    <button type="submit">Abrir</button>
</form>

<?php if (!empty($rootPath)): ?>
    <div class="workspace-meta">
        <div><strong>Workspace:</strong></div>
        <div class="workspace-path"><?= htmlspecialchars($rootPath) ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($currentPath)): ?>
    <div class="workspace-nav">
        <a href="<?= htmlspecialchars($baseUrl) ?>/workspace?path=<?= urlencode(dirname($currentPath)) ?>">
            ← Voltar
        </a>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<ul class="file-tree">
    <?php foreach ($items as $item): ?>
        <li class="file-tree-item">
            <?php if ($item['type'] === 'dir'): ?>
                <span class="file-icon">📁</span>
                <a href="<?= htmlspecialchars($baseUrl) ?>/workspace?path=<?= urlencode($item['path']) ?>">
                    <?= htmlspecialchars($item['name']) ?>
                </a>
            <?php else: ?>
                <span class="file-icon">📄</span>
                <a href="<?= htmlspecialchars($baseUrl) ?>/workspace?path=<?= urlencode($currentPath ?? '') ?>&file=<?= urlencode($item['path']) ?>">
                    <?= htmlspecialchars($item['name']) ?>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>