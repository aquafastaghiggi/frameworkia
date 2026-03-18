<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Logger;
use App\UI\TerminalEmulator;
use RuntimeException;

class TerminalController extends Controller
{
    protected TerminalEmulator $terminal;
    protected Logger $logger;

    public function __construct(View $view, Response $response, Logger $logger)
    {
        parent::__construct($view, $response, $logger);
        $basePath = dirname(__DIR__, 3);
        $this->terminal = new TerminalEmulator($basePath);
        $this->logger = $logger;
    }

    /**
     * Executar um comando no terminal
     */
    public function execute(Request $request): void
    {
        $command = (string) $request->input('command', '');

        if ($command === '') {
            $this->json([
                'success' => false,
                'output' => 'Nenhum comando foi informado.',
            ], 400);
            return;
        }

        try {
            $result = $this->terminal->execute($command);

            $this->logger->info("Comando executado: {$command}", [], 'terminal');

            $this->json([
                'success' => $result['success'],
                'output' => $result['output'],
                'error' => $result['error'] ?? false,
                'code' => $result['code'] ?? 0,
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error("Erro ao executar comando: {$e->getMessage()}", ['command' => $command], 'terminal');

            $this->json([
                'success' => false,
                'output' => 'Erro ao executar comando: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obter o histórico de comandos
     */
    public function getHistory(Request $request): void
    {
        $history = $this->terminal->getHistory();

        $this->json([
            'success' => true,
            'history' => $history,
            'count' => count($history),
        ]);
    }

    /**
     * Limpar o histórico de comandos
     */
    public function clearHistory(Request $request): void
    {
        $this->terminal->clearHistory();

        $this->json([
            'success' => true,
            'message' => 'Histórico limpo com sucesso.',
        ]);
    }

    /**
     * Definir o diretório de trabalho
     */
    public function setWorkingDirectory(Request $request): void
    {
        $directory = (string) $request->input('directory', '');

        if ($directory === '') {
            $this->json([
                'success' => false,
                'message' => 'Nenhum diretório foi informado.',
            ], 400);
            return;
        }

        try {
            $this->terminal->setWorkingDirectory($directory);

            $this->json([
                'success' => true,
                'message' => 'Diretório de trabalho alterado com sucesso.',
                'directory' => $this->terminal->getWorkingDirectory(),
            ]);
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => 'Erro ao alterar diretório: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obter o diretório de trabalho atual
     */
    public function getWorkingDirectory(Request $request): void
    {
        $directory = $this->terminal->getWorkingDirectory();

        $this->json([
            'success' => true,
            'directory' => $directory,
        ]);
    }
}
