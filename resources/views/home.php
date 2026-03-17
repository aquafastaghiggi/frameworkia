<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Home') ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($title ?? '') ?></h1>
    <p><?= htmlspecialchars($message ?? '') ?></p>
</body>
</html>