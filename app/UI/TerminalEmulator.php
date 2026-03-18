<?php

declare(strict_types=1);

namespace App\UI;

use RuntimeException;

/**
 * Emulador de Terminal Web
 * Permite executar comandos e capturar a saída em tempo real
 */
class TerminalEmulator
{
    /**
     * @var string
     */
    private string $workingDirectory;

    /**
     * @var array<string>
     */
    private array $history = [];

    /**
     * @var int
     */
    private int $historyIndex = 0;

    /**
     * Construtor
     */
    public function __construct(string $workingDirectory = '/home/ubuntu')
    {
        $this->workingDirectory = $workingDirectory;
    }

    /**
     * Executar um comando no terminal
     */
    public function execute(string $command): array
    {
        // Adicionar ao histórico
        $this->history[] = $command;
        $this->historyIndex = count($this->history);

        // Validar comando (prevenir execução de comandos perigosos)
        if ($this->isDangerousCommand($command)) {
            return [
                'success' => false,
                'output' => 'Erro: Comando não permitido por questões de segurança.',
                'error' => true,
            ];
        }

        try {
            // Executar comando com timeout de 10 segundos
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open(
                "cd {$this->workingDirectory} && {$command}",
                $descriptorspec,
                $pipes
            );

            if (!is_resource($process)) {
                throw new RuntimeException('Falha ao abrir o processo.');
            }

            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            return [
                'success' => $returnCode === 0,
                'output' => $output ?: $error,
                'error' => $returnCode !== 0,
                'code' => $returnCode,
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'output' => 'Erro: ' . $e->getMessage(),
                'error' => true,
            ];
        }
    }

    /**
     * Obter o histórico de comandos
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Obter o comando anterior no histórico
     */
    public function getPreviousCommand(): ?string
    {
        if ($this->historyIndex > 0) {
            $this->historyIndex--;
            return $this->history[$this->historyIndex] ?? null;
        }
        return null;
    }

    /**
     * Obter o próximo comando no histórico
     */
    public function getNextCommand(): ?string
    {
        if ($this->historyIndex < count($this->history) - 1) {
            $this->historyIndex++;
            return $this->history[$this->historyIndex] ?? null;
        }
        return null;
    }

    /**
     * Limpar o histórico
     */
    public function clearHistory(): void
    {
        $this->history = [];
        $this->historyIndex = 0;
    }

    /**
     * Definir o diretório de trabalho
     */
    public function setWorkingDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $this->workingDirectory = $directory;
        }
    }

    /**
     * Obter o diretório de trabalho atual
     */
    public function getWorkingDirectory(): string
    {
        return $this->workingDirectory;
    }

    /**
     * Verificar se o comando é perigoso
     */
    private function isDangerousCommand(string $command): bool
    {
        $dangerousPatterns = [
            '/^rm\s+-rf\s+\//i',  // rm -rf /
            '/^mkfs/i',            // mkfs
            '/^dd\s+if=\/dev\/zero\s+of=\//i',  // dd destruição
            '/>\s*\/dev\/sda/i',   // Sobrescrever disco
            '/sudo\s+reboot/i',    // Reboot
            '/sudo\s+shutdown/i',  // Shutdown
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        return false;
    }
}
