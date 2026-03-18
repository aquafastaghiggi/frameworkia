<?php

declare(strict_types=1);

require_once __DIR__ . 
'/vendor/autoload.php';

use App\Core\Application;
use App\AI\AiResponseStore;
use App\AI\ChatService;
use App\AI\MockAIProvider;
use App\AI\OpenAIProvider;
use App\Queue\QueueService;
use App\Chat\ChatHistoryManager;
use App\Workspace\WorkspaceManager;
use App\Git\GitService;
use App\Documents\DocumentManager;
use App\Workspace\FileTree;
use App\Core\Logger;

// Inicializa a aplicação para ter acesso às configurações e ao logger
    $app = new Application(dirname(__DIR__, 2));
    $logger = new Logger(dirname(__DIR__, 2));

    $queueService = new QueueService(dirname(__DIR__, 2));
    $chatHistoryManager = new ChatHistoryManager(dirname(__DIR__, 2));
    $aiResponseStore = new AiResponseStore(dirname(__DIR__, 2));

// Configura o provedor de IA
        $aiConfig = require dirname(__DIR__, 2) . 
'/config/ai.php';
$providerName = $aiConfig[
'provider'
] ?? 'mock';

if ($providerName === 'openai') {
    $aiProvider = new OpenAIProvider($aiConfig[
'openai'
] ?? []);
} else {
    $aiProvider = new MockAIProvider();
}

$chatService = new ChatService($aiProvider);
$chatService->setLogger($logger);

// Instancia outros serviços necessários para o contexto da IA
    $workspace = new WorkspaceManager(dirname(__DIR__, 2));
$git = new GitService();
$documents = new DocumentManager();
$fileTree = new FileTree();

$logger->info('Worker da fila iniciado.', [], 'queue');

while (true) {
    try {
        $job = $queueService->getNextJob();

        if ($job) {
            $logger->info('Processando job: ' . $job[
'id'
], $job, 'queue');

            switch ($job[
'type'
]) {
                case 'ai_chat':
                    $payload = $job[
'payload'
];
                    $prompt = $payload[
'prompt'
];
                    $context = $payload[
'context'
];

                    // Reconstroi o contexto completo para a IA
                    $fullContext = [
                        'workspace' => $workspace->getRootPath() ?? '',
                        'file_path' => $context[
'file_path'
] ?? '',
                        'current_path' => $context[
'current_path'
] ?? '',
                        'file_content' => $context[
'file_content'
] ?? '',
                        'git_diff' => $context[
'git_diff'
] ?? '',
                        'project_structure' => $context[
'project_structure'
] ?? '',
                        'attachments' => $context[
'attachments'
] ?? [],
                        'role' => $context[
'role'
] ?? 'dev',
                    ];

                    $result = $chatService->send($prompt, $fullContext);

                    if ($result[
'success'
]) {
                        $chatHistoryManager->addMessage("assistant", $result["response"]);
                        $aiResponseStore->save($result["response"], $context['file_path'] ?? '', [
                            'prompt' => $prompt,
                            'job_id' => $job['id'],
                        ]);

                        $queueService->markJobAsCompleted($job[
'id'
], ['response' => $result["response"]]);
                        $logger->info('Job ' . $job[
'id'
] . ' concluído com sucesso.', [], 'queue');
                    } else {
                        $queueService->markJobAsFailed($job[
'id'
], $result[
'message'
] ?? 'Erro desconhecido ao processar IA.');
                        $logger->error('Job ' . $job[
'id'
] . ' falhou: ' . ($result[
'message'
] ?? 'Erro desconhecido'), [], 'queue');
                    }
                    break;
                default:
                    $queueService->markJobAsFailed($job[
'id'
], 'Tipo de job desconhecido: ' . $job[
'type'
]);
                    $logger->warning('Tipo de job desconhecido: ' . $job[
'type'
], [], 'queue');
                    break;
            }
        } else {
            // Sem jobs, espera um pouco antes de verificar novamente
            sleep(5);
        }
    } catch (\Throwable $e) {
        $logger->error('Erro no worker da fila: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], 'queue');
        // Evita loop infinito em caso de erro persistente
        sleep(10);
    }
}
