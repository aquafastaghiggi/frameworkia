<?php
$baseUrl = $baseUrl ?? \App\Core\Application::config('app.url');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Login') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <?= $content ?? '' ?>
    </div>
</body>
</html>
