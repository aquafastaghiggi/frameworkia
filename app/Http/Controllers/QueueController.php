<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Logger;
use App\Queue\QueueService;
use App\Security\PermissionGuard;
use RuntimeException;

class QueueController extends Controller
{
    protected QueueService $queueService;

    public function __construct(View $view, Response $response, Logger $logger)
    {
        parent::__construct($view, $response, $logger);
        $basePath = dirname(__DIR__, 3);
        $this->queueService = new QueueService($basePath);
    }

    public function startWorker(Request $request): void
    {
        PermissionGuard::requireRole();
        // Comando para iniciar o worker em segundo plano
        $command = 'php ' . dirname(__DIR__, 3) . '/worker.php > /dev/null 2>&1 &';
        
        try {
            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $this->success('Worker da fila iniciado com sucesso em segundo plano.');
            } else {
                $this->error('Falha ao iniciar o worker da fila.', 500, ['output' => $output]);
            }
        } catch (RuntimeException $e) {
            $this->error('Erro ao executar comando: ' . $e->getMessage(), 500);
        }
    }

    public function getJobs(Request $request): void
    {
        PermissionGuard::requireRole();
        try {
            $jobs = $this->queueService->getAllJobs();
            $this->success('Jobs da fila recuperados com sucesso.', ['jobs' => $jobs]);
        } catch (RuntimeException $e) {
            $this->error('Erro ao recuperar jobs da fila: ' . $e->getMessage(), 500);
        }
    }

    public function clearCompletedJobs(Request $request): void
    {
        PermissionGuard::requireRole();
        try {
            $this->queueService->clearCompletedJobs();
            $this->success('Jobs concluídos da fila limpos com sucesso.');
        } catch (RuntimeException $e) {
            $this->error('Erro ao limpar jobs concluídos da fila: ' . $e->getMessage(), 500);
        }
    }
}
