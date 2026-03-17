<?php

declare(strict_types=1);

namespace App\Uploads;

use RuntimeException;

class UploadService
{
    protected array $allowedExtensions = [
        'txt', 'md', 'json', 'csv',
        'pdf',
        'xlsx', 'xls',
        'png', 'jpg', 'jpeg', 'webp',
    ];

    protected int $maxFileSize = 10_000_000; // 10 MB

    public function __construct(
        protected string $basePath
    ) {
    }

    protected function uploadDirectory(): string
    {
        return $this->basePath . '/storage/uploads';
    }

    public function ensureUploadDirectory(): void
    {
        $dir = $this->uploadDirectory();

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException('Não foi possível criar a pasta de uploads.');
            }
        }
    }

    public function upload(array $file): array
    {
        $this->ensureUploadDirectory();

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload do arquivo.');
        }

        $originalName = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($originalName === '' || $tmpName === '') {
            throw new RuntimeException('Arquivo inválido.');
        }

        if ($size <= 0) {
            throw new RuntimeException('Arquivo vazio.');
        }

        if ($size > $this->maxFileSize) {
            throw new RuntimeException('Arquivo excede o limite de 10 MB.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedExtensions, true)) {
            throw new RuntimeException('Tipo de arquivo não permitido.');
        }

        $safeBaseName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $finalName = date('Ymd_His') . '_' . $safeBaseName . '.' . $extension;
        $destination = $this->uploadDirectory() . '/' . $finalName;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Não foi possível mover o arquivo enviado.');
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $finalName,
            'path' => $destination,
            'relative_path' => 'storage/uploads/' . $finalName,
            'extension' => $extension,
            'size' => $size,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function listUploads(): array
    {
        $this->ensureUploadDirectory();

        $files = scandir($this->uploadDirectory());

        if ($files === false) {
            return [];
        }

        $result = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $this->uploadDirectory() . '/' . $file;

            if (!is_file($fullPath)) {
                continue;
            }

            $result[] = [
                'stored_name' => $file,
                'relative_path' => 'storage/uploads/' . $file,
                'extension' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                'size' => filesize($fullPath) ?: 0,
                'modified_at' => date('Y-m-d H:i:s', filemtime($fullPath) ?: time()),
            ];
        }

        usort($result, fn(array $a, array $b) => strcmp($b['modified_at'], $a['modified_at']));

        return $result;
    }
}