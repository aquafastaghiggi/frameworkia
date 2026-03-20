<?php

declare(strict_types=1);

namespace App\Workspace;

use RuntimeException;

class WorkspaceManager
{
    public function __construct(
        protected string $basePath
    ) {
    }

    protected function workspaceFile(): string
    {
        return $this->basePath . '/storage/workspace.json';
    }

    public function setRootPath(string $path): void
    {
        $realPath = realpath($path);

        if ($realPath === false || !is_dir($realPath)) {
            throw new RuntimeException('Diretório de workspace inválido.');
        }

        $storageDir = dirname($this->workspaceFile());

        if (!is_dir($storageDir)) {
            if (!mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
                throw new RuntimeException('Não foi possível criar a pasta storage.');
            }
        }

        $data = [
            'root_path' => $realPath,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $result = file_put_contents(
            $this->workspaceFile(),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        if ($result === false) {
            throw new RuntimeException('Não foi possível salvar o arquivo de workspace.');
        }
    }

    public function getRootPath(): ?string
    {
        $file = $this->workspaceFile();

        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = json_decode($content ?: '{}', true);

        return $data['root_path'] ?? null;
    }

    public function hasWorkspace(): bool
    {
        $root = $this->getRootPath();
        return is_string($root) && is_dir($root);
    }

    public function listFiles(string $relativePath = ''): array
    {
        $root = $this->getRootPath();

        if ($root === null) {
            throw new RuntimeException('Nenhum workspace configurado.');
        }

        $targetPath = $this->resolvePath($relativePath);

        if (!is_dir($targetPath)) {
            throw new RuntimeException('Diretório não encontrado.');
        }

        $items = scandir($targetPath);

        if ($items === false) {
            throw new RuntimeException('Falha ao listar diretório.');
        }

        $result = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if ($item === '.git' || $item === 'vendor' || $item === 'node_modules') {
                continue;
            }

            $fullPath = $targetPath . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($fullPath);

            $relative = ltrim(str_replace($root, '', $fullPath), DIRECTORY_SEPARATOR);
            $relative = str_replace('\\', '/', $relative);

            $result[] = [
                'name' => $item,
                'path' => $relative,
                'type' => $isDir ? 'dir' : 'file',
            ];
        }

        usort($result, function (array $a, array $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    public function readFile(string $relativePath): string
    {
        $path = $this->resolvePath($relativePath);

        if (!is_file($path)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $blockedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'exe', 'dll', 'zip', 'rar', '7z', 'pdf'];

        if (in_array($extension, $blockedExtensions, true)) {
            throw new RuntimeException('Tipo de arquivo não suportado para visualização.');
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o arquivo.');
        }

        return $content;
    }

    public function writeFile(string $relativePath, string $content): void
    {
        $path = $this->resolvePath($relativePath);

        if (!is_file($path)) {
            throw new RuntimeException('Arquivo não encontrado para escrita.');
        }

        if (!is_writable($path)) {
            throw new RuntimeException('Arquivo sem permissão de escrita.');
        }

        $result = file_put_contents($path, $content);

        if ($result === false) {
            throw new RuntimeException('Não foi possível salvar o arquivo.');
        }
    }

    public function createBackup(string $relativePath): string
    {
        $path = $this->resolvePath($relativePath);

        if (!is_file($path)) {
            throw new RuntimeException('Arquivo não encontrado para backup.');
        }

        $backupPath = $path . '.ai.bak';

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o arquivo para backup.');
        }

        $result = file_put_contents($backupPath, $content);

        if ($result === false) {
            throw new RuntimeException('Não foi possível criar o backup do arquivo.');
        }

        return $backupPath;
    }

    public function restoreBackup(string $relativePath): void
    {
        $path = $this->resolvePath($relativePath);
        $backupPath = $path . '.ai.bak';

        if (!is_file($backupPath)) {
            throw new RuntimeException('Nenhum backup encontrado para este arquivo.');
        }

        $content = file_get_contents($backupPath);

        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o backup.');
        }

        $result = file_put_contents($path, $content);

        if ($result === false) {
            throw new RuntimeException('Não foi possível restaurar o backup.');
        }
    }

    public function deleteBackup(string $relativePath): void
    {
        $path = $this->resolvePath($relativePath);
        $backupPath = $path . '.ai.bak';

        if (is_file($backupPath)) {
            @unlink($backupPath);
        }
    }

    public function resolvePath(string $relativePath): string
    {
        $root = $this->getRootPath();

        if ($root === null) {
            throw new RuntimeException('Nenhum workspace configurado.');
        }

        $relativePath = trim($relativePath);
        $relativePath = str_replace(['\\', '..'], ['/', ''], $relativePath);
        $relativePath = ltrim($relativePath, '/');

        $candidate = $root . DIRECTORY_SEPARATOR . $relativePath;

        $realCandidate = realpath($candidate);

        if ($realCandidate === false) {
            if (file_exists($candidate)) {
                $realCandidate = $candidate;
            } else {
                throw new RuntimeException('Caminho inválido.');
            }
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedCandidate = str_replace('\\', '/', $realCandidate);

        if (!str_starts_with($normalizedCandidate, $normalizedRoot)) {
            throw new RuntimeException('Acesso fora do workspace não permitido.');
        }

        return $realCandidate;
    }
}