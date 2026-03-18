<?php
$baseUrl = $baseUrl ?? \App\Core\Application::config('app.url');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'IDE') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/ide.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/split-editor-terminal.css">
</head>
<body>
    <div class="ide-shell">
        <header class="topbar">
            <div class="topbar-left">
                <strong>Mini Framework IA</strong>
            </div>
            <div class="topbar-center">
                Workspace IDE Local
            </div>
            <div class="topbar-right">
                PHP • Local
            </div>
        </header>

        <?= $content ?? '' ?>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>/assets/js/editor.js"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>/assets/js/chat.js"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>/assets/js/split-editor.js"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>/assets/js/terminal.js"></script>
</body>
</html>
