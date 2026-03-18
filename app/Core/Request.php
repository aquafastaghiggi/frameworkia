<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    protected ?array $jsonData = null;
    protected array $testData = [];

    public function __construct(array $testData = [])
    {
        $this->testData = $testData;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $scriptBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptBase = rtrim($scriptBase, '/');

        if ($scriptBase !== '' && $scriptBase !== '/' && str_starts_with($uri, $scriptBase)) {
            $uri = substr($uri, strlen($scriptBase));
        }

        return $uri === '' ? '/' : $uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->testData)) {
            return $this->testData[$key];
        }

        $json = $this->json();

        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $_POST[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $_GET;
    }

    public function allInput(): array
    {
        return array_merge($_POST, $this->json());
    }

    public function json(): array
    {
        if ($this->jsonData !== null) {
            return $this->jsonData;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!str_contains($contentType, 'application/json')) {
            $this->jsonData = [];
            return $this->jsonData;
        }

        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            $this->jsonData = [];
            return $this->jsonData;
        }

        $decoded = json_decode($raw, true);

        $this->jsonData = is_array($decoded) ? $decoded : [];
        return $this->jsonData;
    }

    public function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}