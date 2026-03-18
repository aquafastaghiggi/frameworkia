<?php

declare(strict_types=1);

namespace App\Workspace;

use App\Cache\FileCacheService;
use RuntimeException;
use Throwable;

class WorkspaceIndexer
{
    protected array $extensions = [
        'php', 'js', 'ts', 'json', 'html', 'css', 'md', 'txt', 'yaml', 'yml', 'env', 'xml',
    ];

    protected array $excludedDirs = [
        '.git', 'vendor', 'node_modules', 'storage', 'public', 'resources',
    ];

    public function __construct(
        protected WorkspaceManager $workspace,
        protected FileCacheService $cacheService
    ) {
    }

    /**
     * Retorna os arquivos mais relevantes para contexto de IA.
     */
    public function getContextFiles(int $limit = 5): array
    {
        $root = $this->workspace->getRootPath();
        if ($root === null) {
            return [];
        }

        $cacheKey = $root . '|workspace_index';
        $index = $this->cacheService->get($cacheKey);

        if (!is_array($index) || empty($index)) {
            $index = $this->buildIndex($root);
            $this->cacheService->put($cacheKey, $index);
        }

        return array_slice($index, 0, $limit);
    }

    protected function buildIndex(string $root): array
    {
        $files = [];
        $this->collectFiles($root, $root, $files);

        usort($files, fn(array $a, array $b) => $b['modified'] <=> $a['modified']);

        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'path' => $file['relative'],
                'summary' => $file['summary'],
                'modified' => $file['modified'],
                'size' => $file['size'],
            ];
        }

        return $result;
    }

    protected function collectFiles(string $root, string $current, array &$files): void
    {
        $items = scandir($current);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (in_array($item, $this->excludedDirs, true)) {
                continue;
            }

            $path = $current . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->collectFiles($root, $path, $files);
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($extension !== '' && !in_array($extension, $this->extensions, true)) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', str_replace($root, '', $path)), '/');
            $summary = $this->summarizeFile($path);

            $files[] = [
                'path' => $path,
                'relative' => $relative,
                'summary' => $summary,
                'modified' => filemtime($path) ?: 0,
                'size' => filesize($path) ?: 0,
            ];
        }
    }

    protected function summarizeFile(string $path): string
    {
        $max = 400;
        try {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Falha ao abrir o arquivo.');
            }
            $content = fread($handle, 1024);
            fclose($handle);
        } catch (Throwable $e) {
            return '';
        }

        if ($content === false) {
            return '';
        }

        $content = trim(preg_replace('/\\s+/', ' ', $content));

        if ($content === '') {
            return '';
        }

        $summary = mb_substr($content, 0, $max);

        if (mb_strlen($content) > $max) {
            $summary .= '…';
        }

        return $summary;
    }
}
