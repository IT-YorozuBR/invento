<?php
declare(strict_types=1);

namespace App\Config;

use mysqli;
use Exception;

class Database
{
    private static ?self $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';
        $name = $_ENV['DB_NAME'] ?? '';

        try {
            $this->connection = new mysqli($host, $user, $pass, $name);

            if ($this->connection->connect_error) {
                throw new Exception('Erro na conexão: ' . $this->connection->connect_error);
            }

            $this->connection->set_charset('utf8mb4');
        } catch (Exception $e) {
            error_log('Database connection error: ' . $e->getMessage());
            die('Erro na conexão com o banco de dados. Por favor, contate o administrador.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    public function prepare(string $sql): \mysqli_stmt
    {
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Erro ao preparar query: ' . $this->connection->error . ' SQL: ' . $sql);
        }
        return $stmt;
    }

    public function query(string $sql): \mysqli_result|bool
    {
        return $this->connection->query($sql);
    }

    public function escape(string $string): string
    {
        return $this->connection->real_escape_string($string);
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }

    public function columnExists(string $table, string $column): bool
    {
        $result = $this->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $result && $result->num_rows > 0;
    }

    public function tableExists(string $table): bool
    {
        $result = $this->query("SHOW TABLES LIKE '{$table}'");
        return $result && $result->num_rows > 0;
    }
}
