<?php

declare(strict_types=1);

/**
 * Frameworkia - Environment Configuration Loader
 * Carrega variáveis de ambiente do arquivo .env
 */

class EnvLoader
{
    private static ?self $instance = null;
    private array $env = [];

    private function __construct()
    {
        $this->load();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load(): void
    {
        $envFile = dirname(__DIR__) . '/.env';

        if (!file_exists($envFile)) {
            // Tenta usar .env.example
            $envFile = dirname(__DIR__) . '/.env.example';
        }

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Ignora comentários
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }

                // Parse key=value
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Remove aspas
                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }

                    $this->env[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        // Define valores padrão se não existem
        $this->setDefault('APP_NAME', 'Frameworkia');
        $this->setDefault('APP_ENV', 'local');
        $this->setDefault('APP_DEBUG', 'false');
        $this->setDefault('APP_URL', 'http://localhost:8000');

        $this->setDefault('DB_CONNECTION', 'mysql');
        $this->setDefault('DB_HOST', '127.0.0.1');
        $this->setDefault('DB_PORT', '3306');
        $this->setDefault('DB_DATABASE', 'frameworkia');
        $this->setDefault('DB_USERNAME', 'root');
        $this->setDefault('DB_PASSWORD', '');

        $this->setDefault('OPENAI_API_KEY', 'mock-key-for-testing');
        $this->setDefault('OPENAI_MODEL', 'gpt-4o-mini');

        $this->setDefault('REDIS_HOST', '127.0.0.1');
        $this->setDefault('REDIS_PORT', '6379');

        $this->setDefault('MAX_UPLOAD_SIZE', '52428800');
        $this->setDefault('RATE_LIMIT_MAX_REQUESTS', '100');
        $this->setDefault('SESSION_TIMEOUT', '1800');
    }

    private function setDefault(string $key, string $value): void
    {
        if (!isset($this->env[$key])) {
            $this->env[$key] = $value;
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->env[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->env[$key] ?? null;
        return $value ? (int)$value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = strtolower($this->env[$key] ?? '');
        return in_array($value, ['true', '1', 'yes', 'on'], true) || $default;
    }

    public function all(): array
    {
        return $this->env;
    }
}

// Global helper function
function env(string $key, ?string $default = null): ?string
{
    return EnvLoader::getInstance()->get($key, $default);
}

function envInt(string $key, int $default = 0): int
{
    return EnvLoader::getInstance()->getInt($key, $default);
}

function envBool(string $key, bool $default = false): bool
{
    return EnvLoader::getInstance()->getBool($key, $default);
}
