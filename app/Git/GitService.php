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

    public function listBranches(string $path): array
    {
        $output = trim($this->runGitCommand($path, ['branch', '--list']));
        if ($output === '') {
            return [];
        }

        $lines = explode("\n", $output);
        $branches = [];
        foreach ($lines as $line) {
            $branches[] = trim(str_replace('*', '', $line));
        }
        return array_values(array_filter($branches));
    }

    public function switchBranch(string $path, string $branch, bool $create = false): string
    {
        $args = ['checkout'];
        
        if ($create) {
            $args[] = '-B'; // Cria se não existir, reseta se já existir
        }
        
        $args[] = $branch;

        return trim($this->runGitCommand($path, $args));
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

    public function getDiff(string $path, string $file = ''): string
    {
        $args = ['diff'];
        if ($file !== '') {
            $args[] = '--';
            $args[] = str_replace('\\', '/', $file);
        }

        return $this->runGitCommand($path, $args, false);
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

    public function pull(string $path, string $remote = 'origin', ?string $branch = null): string
    {
        $args = ['pull', $remote];
        if ($branch) {
            $args[] = $branch;
        }
        return trim($this->runGitCommand($path, $args));
    }

    public function push(string $path, string $remote = 'origin', ?string $branch = null): string
    {
        $args = ['push', $remote];
        if ($branch) {
            $args[] = $branch;
        }
        return trim($this->runGitCommand($path, $args));
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
        $exitCode = 0;
        
        // shell_exec não retorna o exit code, vamos usar exec para comandos que precisam de validação rigorosa
        if ($throwOnError) {
            exec($command, $execOutput, $exitCode);
            if ($exitCode !== 0) {
                $errorMsg = !empty($execOutput) ? implode("\n", $execOutput) : 'Erro desconhecido ao executar comando Git.';
                throw new RuntimeException("Git Error ($exitCode): " . $errorMsg);
            }
            return implode("\n", $execOutput);
        }

        return $output ?? '';
    }
}
