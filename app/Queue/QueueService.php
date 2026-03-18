<?php

declare(strict_types=1);

namespace App\Queue;

use RuntimeException;

class QueueService
{
    protected string $queueFilePath;

    public function __construct(string $basePath)
    {
        $this->queueFilePath = $basePath . 
'/storage/queue/ai_jobs.json';
        $queueDir = dirname($this->queueFilePath);

        if (!is_dir($queueDir)) {
            if (!mkdir($queueDir, 0777, true) && !is_dir($queueDir)) {
                throw new RuntimeException('Não foi possível criar o diretório da fila.');
            }
        }
    }

    public function addJob(string $jobType, array $payload): void
    {
        $jobs = $this->loadJobs();
        $jobs[] = [
            'id' => uniqid('job_'),
            'type' => $jobType,
            'payload' => $payload,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->saveJobs($jobs);
    }

    public function getNextJob(): ?array
    {
        $jobs = $this->loadJobs();
        foreach ($jobs as $index => $job) {
            if ($job['status'] === 'pending') {
                $jobs[$index]['status'] = 'processing';
                $this->saveJobs($jobs);
                return $job;
            }
        }
        return null;
    }

    public function markJobAsCompleted(string $jobId, array $result = []): void
    {
        $jobs = $this->loadJobs();
        foreach ($jobs as $index => $job) {
            if ($job['id'] === $jobId) {
                $jobs[$index]['status'] = 'completed';
                $jobs[$index]['completed_at'] = date('Y-m-d H:i:s');
                $jobs[$index]['result'] = $result;
                $this->saveJobs($jobs);
                return;
            }
        }
    }

    public function markJobAsFailed(string $jobId, string $error): void
    {
        $jobs = $this->loadJobs();
        foreach ($jobs as $index => $job) {
            if ($job['id'] === $jobId) {
                $jobs[$index]['status'] = 'failed';
                $jobs[$index]['failed_at'] = date('Y-m-d H:i:s');
                $jobs[$index]['error'] = $error;
                $this->saveJobs($jobs);
                return;
            }
        }
    }

    protected function loadJobs(): array
    {
        if (!file_exists($this->queueFilePath)) {
            return [];
        }

        $content = file_get_contents($this->queueFilePath);
        if ($content === false) {
            return [];
        }

        $jobs = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return is_array($jobs) ? $jobs : [];
    }

    protected function saveJobs(array $jobs): void
    {
        $content = json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($content === false) {
            throw new RuntimeException('Erro ao codificar jobs para fila: ' . json_last_error_msg());
        }

        if (file_put_contents($this->queueFilePath, $content) === false) {
            throw new RuntimeException('Não foi possível salvar o arquivo da fila.');
        }
    }

    public function getAllJobs(): array
    {
        return $this->loadJobs();
    }

    public function clearCompletedJobs(): void
    {
        $jobs = array_filter($this->loadJobs(), fn($job) => $job['status'] !== 'completed');
        $this->saveJobs($jobs);
    }
}
