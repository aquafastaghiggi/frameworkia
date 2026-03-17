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
        try {
            if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
                throw new RuntimeException('Nenhum arquivo enviado.');
            }

            $uploaded = $this->uploads->upload($_FILES['attachment']);

            if (!isset($_SESSION['uploaded_attachments']) || !is_array($_SESSION['uploaded_attachments'])) {
                $_SESSION['uploaded_attachments'] = [];
            }

            array_unshift($_SESSION['uploaded_attachments'], $uploaded);
            $_SESSION['uploaded_attachments'] = array_slice($_SESSION['uploaded_attachments'], 0, 20);

            $this->json([
                'success' => true,
                'message' => 'Arquivo enviado com sucesso.',
                'file' => $uploaded,
                'attachments' => $_SESSION['uploaded_attachments'],
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function delete(Request $request): void
    {
        try {
            $relativePath = (string) $request->input('path');

            if ($relativePath === '') {
                throw new RuntimeException('Arquivo não informado.');
            }

            $basePath = dirname(__DIR__, 3);
            $fullPath = $basePath . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

            if (is_file($fullPath)) {
                @unlink($fullPath);
            }

            if (isset($_SESSION['uploaded_attachments']) && is_array($_SESSION['uploaded_attachments'])) {
                $_SESSION['uploaded_attachments'] = array_values(array_filter(
                    $_SESSION['uploaded_attachments'],
                    fn($a) => ($a['relative_path'] ?? '') !== $relativePath
                ));
            }

            $this->json([
                'success' => true,
                'message' => 'Anexo removido.',
                'attachments' => $_SESSION['uploaded_attachments'] ?? [],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}