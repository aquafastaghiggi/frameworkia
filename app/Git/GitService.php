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

    public function unstageFile(string $path, string $file): void
    {
        $normalizedFile = str_replace('\\', '/', $file);
        $this->runGitCommand($path, ['restore', '--staged', '--', $normalizedFile]);
    }

    public function push(string $path, ?string $remote = null, ?string $branch = null): string
    {
        $args = ['push'];
        
        if ($remote !== null) {
            $args[] = $remote;
        }
        
        if ($branch !== null) {
            $args[] = $branch;
        }
        
        return trim($this->runGitCommand($path, $args));
    }

    public function pull(string $path, ?string $remote = null, ?string $branch = null): string
    {
        $args = ['pull'];
        
        if ($remote !== null) {
            $args[] = $remote;
        }
        
        if ($branch !== null) {
            $args[] = $branch;
        }
        
        return trim($this->runGitCommand($path, $args));
    }

    public function fetch(string $path, ?string $remote = null): string
    {
        $args = ['fetch'];
        
        if ($remote !== null) {
            $args[] = $remote;
        }
        
        return trim($this->runGitCommand($path, $args));
    }

    public function getBranches(string $path): array
    {
        $output = trim($this->runGitCommand($path, ['branch', '-a']));

        if ($output === '') {
            return [];
        }

        $branches = [
            'local' => [],
            'remote' => [],
        ];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            
            if ($line === '') {
                continue;
            }

            // Remove leading '* ' for current branch
            $isCurrent = str_starts_with($line, '* ');
            $branchName = $isCurrent ? substr($line, 2) : $line;

            if (str_starts_with($branchName, 'remotes/')) {
                $branches['remote'][] = [
                    'name' => str_replace('remotes/', '', $branchName),
                    'current' => false,
                ];
            } else {
                $branches['local'][] = [
                    'name' => $branchName,
                    'current' => $isCurrent,
                ];
            }
        }

        return $branches;
    }

    public function createBranch(string $path, string $branchName): string
    {
        $branchName = trim($branchName);

        if ($branchName === '') {
            throw new RuntimeException('Nome da branch não pode estar vazio.');
        }

        return trim($this->runGitCommand($path, ['branch', $branchName]));
    }

    public function deleteBranch(string $path, string $branchName, bool $force = false): string
    {
        $branchName = trim($branchName);

        if ($branchName === '') {
            throw new RuntimeException('Nome da branch não pode estar vazio.');
        }

        $args = ['branch', $force ? '-D' : '-d', $branchName];
        
        return trim($this->runGitCommand($path, $args));
    }

    public function switchBranch(string $path, string $branchName): string
    {
        $branchName = trim($branchName);

        if ($branchName === '') {
            throw new RuntimeException('Nome da branch não pode estar vazio.');
        }

        return trim($this->runGitCommand($path, ['switch', $branchName]));
    }

    public function getCommitHistory(string $path, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $format = '%H%n%an%n%ae%n%ad%n%s%n---COMMIT---';
        $output = $this->runGitCommand($path, [
            'log',
            '--pretty=format:' . $format,
            '-n', (string) $limit,
            '--date=iso-strict',
        ]);

        if (trim($output) === '') {
            return [];
        }

        $commits = [];
        $commitBlocks = explode('---COMMIT---', $output);

        foreach ($commitBlocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines = explode("\n", $block);
            $lines = array_map('trim', $lines);
            
            // Need at least 5 lines: hash, author, email, date, message
            if (count($lines) >= 5) {
                // Verify hash is not empty and looks like a git hash
                if ($lines[0] !== '' && preg_match('/^[a-f0-9]{7,40}$/', $lines[0])) {
                    $commits[] = [
                        'hash' => $lines[0],
                        'author' => $lines[1] ?? '',
                        'email' => $lines[2] ?? '',
                        'date' => $lines[3] ?? '',
                        'message' => $lines[4] ?? '',
                    ];
                }
            }
        }

        return $commits;
    }

    public function getCommitDetails(string $path, string $hash): array
    {
        $hash = trim($hash);

        if (!preg_match('/^[a-f0-9]{7,40}$/', $hash)) {
            throw new RuntimeException('Hash de commit inválido.');
        }

        $format = '%H%n%P%n%an%n%ae%n%ad%n%s%n%b';
        $output = trim($this->runGitCommand($path, [
            'show',
            '--pretty=format:' . $format,
            $hash,
        ]));

        $lines = array_map('trim', explode("\n", $output));
        
        return [
            'hash' => $lines[0] ?? '',
            'parents' => array_filter(array_map('trim', explode(' ', $lines[1] ?? ''))),
            'author' => $lines[2] ?? '',
            'email' => $lines[3] ?? '',
            'date' => $lines[4] ?? '',
            'subject' => $lines[5] ?? '',
            'body' => implode("\n", array_slice($lines, 6)),
        ];
    }

    public function getFilesInCommit(string $path, string $hash): array
    {
        $hash = trim($hash);

        if (!preg_match('/^[a-f0-9]{7,40}$/', $hash)) {
            throw new RuntimeException('Hash de commit inválido.');
        }

        $output = trim($this->runGitCommand($path, [
            'diff-tree',
            '--no-commit-id',
            '-r',
            $hash,
        ]));

        if ($output === '') {
            return [];
        }

        $files = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 5) {
                $files[] = [
                    'status' => $parts[4],
                    'path' => implode(' ', array_slice($parts, 5)),
                ];
            }
        }

        return $files;
    }

    public function getDiffBetweenCommits(string $path, string $commit1, string $commit2): string
    {
        $commit1 = trim($commit1);
        $commit2 = trim($commit2);

        if (!preg_match('/^[a-f0-9]{7,40}$/', $commit1) || !preg_match('/^[a-f0-9]{7,40}$/', $commit2)) {
            throw new RuntimeException('Hash de commit inválido.');
        }

        return $this->runGitCommand($path, ['diff', $commit1, $commit2], false);
    }

    public function getRemotes(string $path): array
    {
        $output = trim($this->runGitCommand($path, ['remote', '-v'], false));

        if ($output === '') {
            return [];
        }

        $remotes = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 3) {
                $name = $parts[0];
                $url = $parts[1];
                $type = trim($parts[2], '()');

                if (!isset($remotes[$name])) {
                    $remotes[$name] = [
                        'name' => $name,
                        'fetch' => null,
                        'push' => null,
                    ];
                }

                if ($type === 'fetch') {
                    $remotes[$name]['fetch'] = $url;
                } elseif ($type === 'push') {
                    $remotes[$name]['push'] = $url;
                }
            }
        }

        return array_values($remotes);
    }

    public function addRemote(string $path, string $name, string $url): string
    {
        $name = trim($name);
        $url = trim($url);

        if ($name === '' || $url === '') {
            throw new RuntimeException('Nome e URL do remote não podem estar vazios.');
        }

        return trim($this->runGitCommand($path, ['remote', 'add', $name, $url]));
    }

    public function removeRemote(string $path, string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Nome do remote não pode estar vazio.');
        }

        return trim($this->runGitCommand($path, ['remote', 'remove', $name]));
    }

    public function discardChanges(string $path, string $file): void
    {
        $normalizedFile = str_replace('\\', '/', $file);
        $this->runGitCommand($path, ['checkout', 'HEAD', '--', $normalizedFile]);
    }

    protected function runGitCommand(string $workingPath, array $arguments, bool $throwOnError = true): string
    {
        $realPath = realpath($workingPath);

        if ($realPath === false || !is_dir($realPath)) {
            throw new RuntimeException('Diretório do repositório inválido.');
        }

        $parts = ['git', '-C', escapeshellarg($realPath)];

        foreach ($arguments as $argument) {
            // Special handling for --pretty=format: arguments
            if (strpos($argument, '--pretty=format:') === 0) {
                // Extract the format string without escaping it
                $parts[] = $argument;
            } else {
                $parts[] = escapeshellarg($argument);
            }
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