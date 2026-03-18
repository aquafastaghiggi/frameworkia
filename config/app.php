<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Mini AI Framework',
        'env' => 'local',
        'debug' => true,
        'url' => 'http://localhost/frameworkia/public',
        'allowed_write_extensions' => [
            'php', 'js', 'json', 'html', 'css', 'md', 'txt', 'xml', 'yaml', 'yml', 'env', 'log',
        ],
    ],
    'cache' => [
        'file_explorer_ttl' => 300, // Tempo de vida do cache em segundos (5 minutos)
    ],
];
