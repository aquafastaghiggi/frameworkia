<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Uploads\UploadService;
use RuntimeException;

class UploadController extends Controller
{
    protected UploadService $uploads;

    public function __construct(View $view, Response $response)
    {
        parent::__construct($view, $response);

        $basePath = dirname(__DIR__, 3);
        $this->uploads = new UploadService($basePath);
    }

    public function upload(Request $request): void
    {
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            throw new RuntimeException('Nenhum arquivo enviado.');
        }

        $uploaded = $this->uploads->upload($_FILES['attachment']);

        if (!isset($_SESSION['uploaded_attachments']) || !is_array($_SESSION['uploaded_attachments'])) {
            $_SESSION['uploaded_attachments'] = [];
        }

        array_unshift($_SESSION['uploaded_attachments'], $uploaded);
        $_SESSION['uploaded_attachments'] = array_slice($_SESSION['uploaded_attachments'], 0, 20);

        $this->success('Arquivo enviado com sucesso.', [
            'file' => $uploaded,
            'attachments' => $_SESSION['uploaded_attachments'],
        ]);
    }

    public function delete(Request $request): void
    {
        $relativePath = (string) $request->input('path');

        if ($relativePath === '') {
            throw new RuntimeException('Arquivo não informado.');
        }

        // Normalizar o caminho relativo para evitar problemas de barra
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        $basePath = dirname(__DIR__, 3);
        $fullPath = $basePath . '/' . $relativePath;

        // Verificar se o arquivo está dentro da pasta de uploads por segurança
        if (!str_starts_with($relativePath, 'storage/uploads/')) {
            throw new RuntimeException('Acesso não permitido para exclusão.');
        }

        if (is_file($fullPath)) {
            if (!@unlink($fullPath)) {
                throw new RuntimeException('Não foi possível excluir o arquivo físico.');
            }
        }

        if (isset($_SESSION['uploaded_attachments']) && is_array($_SESSION['uploaded_attachments'])) {
            $_SESSION['uploaded_attachments'] = array_values(array_filter(
                $_SESSION['uploaded_attachments'],
                function($a) use ($relativePath) {
                    $itemPath = ltrim(str_replace('\\', '/', $a['relative_path'] ?? ''), '/');
                    return $itemPath !== $relativePath;
                }
            ));
        }

        $this->success('Anexo removido com sucesso.', [
            'path' => $relativePath,
            'attachments' => $_SESSION['uploaded_attachments'] ?? [],
        ]);
    }
}