<div class="ide-main with-chat">
    <aside class="sidebar">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
    </aside>
    <main class="editor-panel">
        <input type="hidden" id="initial-file-path" value="<?= htmlspecialchars($filePath ?? '') ?>">
        <input type="hidden" id="initial-file-content" value="<?= htmlspecialchars($fileContent ?? '') ?>">
        <div id="split-editor-container" style="width: 100%; height: 100%;"></div>
        <div class="terminal-container" id="terminal-container" style="width: 100%; height: 300px;"></div>
    </main>

    <aside class="inspector-panel">
        <?php require __DIR__ . '/partials/inspector.php'; ?>
    </aside>

    <aside class="chat-panel">
        <?php require __DIR__ . '/partials/chat.php'; ?>
    </aside>
</div>

<footer class="statusbar">
    <?php require __DIR__ . '/partials/statusbar.php'; ?>
</footer>
