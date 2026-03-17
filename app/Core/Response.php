<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class Response
{
    public function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
        exit;
    }

    public function json(array $data, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }

    public function success(string $message = 'Operação realizada com sucesso', array $data = [], int $status = 200): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $status);
    }

    public function error(string $message = 'Ocorreu um erro inesperado', int $status = 400, array $details = []): void
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        $this->json($response, $status);
    }

    public function exception(Throwable $e): void
    {
        $status = $e->getCode();
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        $details = [];
        
        // Em ambiente de desenvolvimento, podemos adicionar o trace
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $details = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ];
        }

        if ($e instanceof ApiException) {
            $details = array_merge($details, $e->getDetails());
        }

        $this->error($e->getMessage(), (int)$status, $details);
    }

    public function text(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $content;
        exit;
    }
}
