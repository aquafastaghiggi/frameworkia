<?php

declare(strict_types=1);

namespace App\Documents\Readers;

interface DocumentReader
{
    /**
     * Lê documento e retorna conteúdo estruturado
     */
    public function ler(string $caminhoArquivo): array;

    /**
     * Extrai metadados do conteúdo
     */
    public function extrairMetadados(string $conteudo): array;

    /**
     * Busca termo no conteúdo
     */
    public function buscar(string $conteudo, string $termo): array;
}
