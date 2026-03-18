<?php

declare(strict_types=1);

namespace App\Workspace;

class FileTree
{
    protected array $ignoreDirs = ['.git', 'vendor', 'node_modules', 'storage', 'public/assets'];
    protected array $ignoreFiles = ['.DS_Store', 'composer.lock', 'package-lock.json'];

    public function generate(string $rootPath, int $maxDepth = 3): string
    {
        if (!is_dir($rootPath)) {
            return "Diretório inválido.";
        }

        return $this->buildTree($rootPath, $maxDepth);
    }

    protected function buildTree(string $dir, int $maxDepth, int $currentDepth = 0, string $prefix = ''): string
    {
        if ($currentDepth >= $maxDepth) {
            return "";
        }

        $output = "";
        $items = scandir($dir);

        if ($items === false) {
            return "";
        }

        // Filtrar itens ignorados
        $items = array_filter($items, function ($item) use ($dir) {
            if (in_array($item, ['.', '..'])) return false;
            if (in_array($item, $this->ignoreDirs)) return false;
            if (in_array($item, $this->ignoreFiles)) return false;
            return true;
        });

        // Ordenar: diretórios primeiro, depois arquivos
        usort($items, function ($a, $b) use ($dir) {
            $isDirA = is_dir($dir . DIRECTORY_SEPARATOR . $a);
            $isDirB = is_dir($dir . DIRECTORY_SEPARATOR . $b);
            if ($isDirA !== $isDirB) {
                return $isDirA ? -1 : 1;
            }
            return strcasecmp($a, $b);
        });

        $count = count($items);
        $i = 0;

        foreach ($items as $item) {
            $i++;
            $isLast = ($i === $count);
            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($fullPath);

            $connector = $isLast ? "└── " : "├── ";
            $output .= $prefix . $connector . $item . ($isDir ? "/" : "") . "\n";

            if ($isDir) {
                $newPrefix = $prefix . ($isLast ? "    " : "│   ");
                $output .= $this->buildTree($fullPath, $maxDepth, $currentDepth + 1, $newPrefix);
            }
        }

        return $output;
    }
}
