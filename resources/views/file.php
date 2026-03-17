<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Arquivo') ?></title>
</head>
<body>
    <h1>Visualização de arquivo</h1>

    <p><strong>Caminho:</strong> <?= htmlspecialchars($path ?? '') ?></p>

    <pre style="background: #f4f4f4; padding: 16px; border: 1px solid #ccc; overflow: auto; white-space: pre-wrap;"><?= htmlspecialchars($content ?? '') ?></pre>

    <p>
        <a href="/framework/public/workspace">Voltar para workspace</a>
    </p>
</body>
</html>