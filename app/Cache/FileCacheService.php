<?php

declare(strict_types=1);

namespace App\Cache;

use App\Core\Application;
use RuntimeException;

class FileCacheService
{
    protected string $cacheDir;
    protected int $cacheTtl;

    public function __construct(string $basePath)
    {
        $this->cacheDir = $basePath . 
'/storage/cache/files';
        $this->cacheTtl = (int) Application::config('cache.file_explorer_ttl', 300); // 5 minutos padrão

        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0777, true) && !is_dir($this->cacheDir)) {
                throw new RuntimeException('Não foi possível criar o diretório de cache de arquivos.');
            }
        }
    }

    protected function getCacheKey(string $path): string
    {
        return md5($path);
    }

    protected function getCacheFilePath(string $path): string
    {
        return $this->cacheDir . 
'/' . $this->getCacheKey($path) . 
'.json';
    }

    public function get(string $path): ?array
    {
        $cacheFilePath = $this->getCacheFilePath($path);

        if (!file_exists($cacheFilePath)) {
            return null;
        }

        if ((time() - filemtime($cacheFilePath)) > $this->cacheTtl) {
            unlink($cacheFilePath);
            return null;
        }

        $content = file_get_contents($cacheFilePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    public function put(string $path, array $data): void
    {
        $cacheFilePath = $this->getCacheFilePath($path);
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($content === false) {
            throw new RuntimeException('Erro ao codificar dados para cache: ' . json_last_error_msg());
        }

        if (file_put_contents($cacheFilePath, $content) === false) {
            throw new RuntimeException('Não foi possível salvar o arquivo de cache.');
        }
    }

    public function forget(string $path): void
    {
        $cacheFilePath = $this->getCacheFilePath($path);
        if (file_exists($cacheFilePath)) {
            unlink($cacheFilePath);
        }
    }

    public function clear(): void
    {
        foreach (glob($this->cacheDir . 
'/*.json') as $file) {
            unlink($file);
        }
    }
}
