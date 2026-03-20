<?php

declare(strict_types=1);

namespace App\Database;

use Exception;
use PDO;
use PDOException;

/**
 * Database Connection Manager
 * Gerencia conexões MySQL usando PDO
 */
class Database
{
    private static ?self $instance = null;
    private ?PDO $connection = null;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect(): void
    {
        try {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $database = env('DB_DATABASE', 'frameworkia');
            $username = env('DB_USERNAME', 'root');
            $password = env('DB_PASSWORD', '');
            $charset = env('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=$charset";

            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

        } catch (PDOException $e) {
            throw new Exception('Erro na conexão com banco de dados: ' . $e->getMessage());
        }
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Erro na query: ' . $e->getMessage());
        }
    }

    public function queryFirst(string $sql, array $params = []): ?array
    {
        $results = $this->query($sql, $params);
        return $results[0] ?? null;
    }

    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception('Erro ao executar: ' . $e->getMessage());
        }
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->execute($sql, array_values($data));

        return (int)$this->connection->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));

        $sql = "UPDATE $table SET $set WHERE $whereClause";
        $params = array_merge(array_values($data), array_values($where));

        $this->execute($sql, $params);

        return $this->connection->lastRowCount() ?? 0;
    }

    public function delete(string $table, array $where): int
    {
        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        $sql = "DELETE FROM $table WHERE $whereClause";

        $this->execute($sql, array_values($where));

        return $this->connection->lastRowCount() ?? 0;
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    public function tableExists(string $table): bool
    {
        $result = $this->queryFirst(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
            [$table]
        );
        return $result !== null;
    }

    public function createTable(string $table, string $schema): bool
    {
        return $this->execute("CREATE TABLE IF NOT EXISTS $table $schema");
    }
}
