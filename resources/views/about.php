<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Sobre') ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($title ?? '') ?></h1>
    <p><?= htmlspecialchars($description ?? '') ?></p>
</body>
</html>