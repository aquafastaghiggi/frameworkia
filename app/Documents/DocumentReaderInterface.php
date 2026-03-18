<?php

declare(strict_types=1);

namespace App\Documents;

interface DocumentReaderInterface
{
    public function supports(string $extension): bool;

    public function read(string $filePath): array;
}
