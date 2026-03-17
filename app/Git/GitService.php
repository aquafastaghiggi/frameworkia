<?php

declare(strict_types=1);

namespace App\Git;

use RuntimeException;

class GitService
{
    public function isRepository(string $path): bool
    {
        $output = $this->runGitCommand($path, ['rev-parse', '--is-inside-work-tree'], false);
        return trim($output) === 'true';
    }

    public function getCurrentBranch(string $path): string
    {
        return trim($this->runGitCommand($path, ['branch', '--show-current']));
    }

    public function getStatus(string $path): array
    {
        $output = trim($this->runGitCommand($path, ['status', '--short']));

        if ($output === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $output))));
    }

    public function getRecentCommits(string $path, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        $output = trim($this->runGitCommand($path, ['log', '--oneline', '-n', (string) $limit]));

        if ($output === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $output))));
    }

    public function getDiff(string $path, string $file): string
    {
        $normalizedFile = str_replace('\\', '/', $file);

        return $this->runGitCommand($path, ['diff', '--', $normalizedFile], false);
    }

    public function stageFile(string $path, string $file): void
    {
        $normalizedFile = str_replace('\\', '/', $file);

        $this->runGitCommand($path, ['add', '--', $normalizedFile]);
    }

    public function commit(string $path, string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            throw new RuntimeException('A mensagem de commit não pode estar vazia.');
        }

        return trim($this->runGitCommand($path, ['commit', '-m', $message]));
    }

    protected function runGitCommand(string $workingPath, array $arguments, bool $throwOnError = true): string
    {
        $realPath = realpath($workingPath);

        if ($realPath === false || !is_dir($realPath)) {
            throw new RuntimeException('Diretório do repositório inválido.');
        }

        $parts = ['git', '-C', escapeshellarg($realPath)];

        foreach ($arguments as $argument) {
            $parts[] = escapeshellarg($argument);
        }

        $command = implode(' ', $parts) . ' 2>&1';

        $output = shell_exec($command);

        if ($output === null) {
            if ($throwOnError) {
                throw new RuntimeException('Falha ao executar comando Git.');
            }

            return '';
        }

        return $output;
    }
}