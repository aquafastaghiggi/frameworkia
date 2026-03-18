<?php

declare(strict_types=1);

namespace App\Queue;

use RuntimeException;

class QueueMetadataService
{
    protected string $queueFilePath;

    public function __construct(string $basePath)
    {
        $this->queueFilePath = $basePath . '/storage/queue/ai_jobs.json';
    }

    public function recordFailure(string $jobId, string $error): void
    {
        $jobs = $this->readJobs();

        foreach ($jobs as &$job) {
            if ($job['id'] === $jobId) {
                $job['attempts'] = (int) ($job['attempts'] ?? 0) + 1;
                $job['last_error'] = $error;
                $job['last_attempted_at'] = date('Y-m-d H:i:s');
                break;
            }
        }

        $this->writeJobs($jobs);
    }

    public function recordSuccess(string $jobId, array $result = []): void
    {
        $jobs = $this->readJobs();

        foreach ($jobs as &$job) {
            if ($job['id'] === $jobId) {
                $job['last_result'] = $result;
                $job['completed_at'] = date('Y-m-d H:i:s');
                break;
            }
        }

        $this->writeJobs($jobs);
    }

    public function getFailedJobs(): array
    {
        return array_filter($this->readJobs(), fn($job) => ($job['status'] ?? '') === 'failed');
    }

    protected function readJobs(): array
    {
        if (!file_exists($this->queueFilePath)) {
            return [];
        }

        $content = file_get_contents($this->queueFilePath);
        if ($content === false) {
            return [];
        }

        $jobs = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($jobs)) {
            return [];
        }

        return $jobs;
    }

    protected function writeJobs(array $jobs): void
    {
        $content = json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($content === false) {
            throw new RuntimeException('Erro ao salvar metadados da fila: ' . json_last_error_msg());
        }

        if (file_put_contents($this->queueFilePath, $content) === false) {
            throw new RuntimeException('Não foi possível persistir os metadados da fila.');
        }
    }
}
