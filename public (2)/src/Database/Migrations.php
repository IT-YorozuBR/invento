<?php

declare(strict_types=1);

namespace App\Database;

use Exception;
use mysqli;

class Migrations
{
    public static function run(): void
    {
        try {
            $port = $_ENV['DB_PORT'] ?? '3307';
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $user = $_ENV['DB_USER'] ?? '';
            $pass = $_ENV['DB_PASS'] ?? '';
            $name = $_ENV['DB_NAME'] ?? '';

            $conn = new mysqli($host, $user, $pass);
            if ($conn->connect_error) {
                throw new Exception('Erro: ' . $conn->connect_error);
            }

            $conn->query("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db($name);

            self::createTables($conn);
            self::seedDefaultUsers($conn);
            self::runAlterations($conn);

            $conn->close();
        } catch (Exception $e) {
            error_log('Migrations error: ' . $e->getMessage());
            die('Erro na inicialização do banco de dados. Contate o administrador.');
        }
    }

    private static function createTables(mysqli $conn): void
    {
        $tables = [
            'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                matricula VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100),
                tipo ENUM('admin','operador','supervisor') DEFAULT 'operador',
                ativo BOOLEAN DEFAULT TRUE,
                data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ultimo_login TIMESTAMP NULL,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tipo_ativo (tipo, ativo),
                INDEX idx_matricula (matricula)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'inventarios' => "CREATE TABLE IF NOT EXISTS inventarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(20) UNIQUE NOT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE NULL,
                descricao VARCHAR(200),
                status ENUM('planejado','aberto','em_andamento','fechado','cancelado') DEFAULT 'planejado',
                tipo ENUM('fisico','ciclico','rotativo') DEFAULT 'fisico',
                admin_id INT NOT NULL,
                responsavel_id INT NULL,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status_data (status, data_inicio),
                INDEX idx_codigo (codigo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'contagens' => "CREATE TABLE IF NOT EXISTS contagens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inventario_id INT NOT NULL,
                usuario_id INT NOT NULL,
                deposito VARCHAR(100) NOT NULL,
                localizacao VARCHAR(50) NULL,
                partnumber VARCHAR(100) NOT NULL,
                descricao_item VARCHAR(200) NULL,
                unidade_medida VARCHAR(20) DEFAULT 'UN',
                lote VARCHAR(50) NULL,
                validade DATE NULL,
                quantidade_primaria DECIMAL(15,4) NOT NULL,
                quantidade_secundaria DECIMAL(15,4) NULL,
                usuario_secundario_id INT NULL,
                quantidade_terceira DECIMAL(15,4) NULL,
                usuario_terceiro_id INT NULL,
                quantidade_final DECIMAL(15,4) NULL,
                data_contagem_primaria TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_contagem_secundaria TIMESTAMP NULL,
                data_contagem_terceira TIMESTAMP NULL,
                status ENUM('primaria','concluida','divergente','pendente_validacao','ajustado','terceira','secundaria') DEFAULT 'primaria',
                precisa_segunda_contagem BOOLEAN DEFAULT FALSE,
                precisa_terceira_contagem BOOLEAN DEFAULT FALSE,
                motivo_divergencia TEXT NULL,
                ajustado_por INT NULL,
                data_ajuste TIMESTAMP NULL,
                observacoes TEXT NULL,
                INDEX idx_inventario_partnumber (inventario_id, partnumber),
                INDEX idx_partnumber_deposito (partnumber, deposito),
                INDEX idx_status (status),
                INDEX idx_deposito (deposito),
                INDEX idx_data_contagem (data_contagem_primaria),
                UNIQUE KEY uk_contagem_unica (inventario_id, deposito, partnumber, lote)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'partnumbers_registrados' => "CREATE TABLE IF NOT EXISTS partnumbers_registrados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                partnumber VARCHAR(100) UNIQUE NOT NULL,
                descricao VARCHAR(200),
                unidade_medida VARCHAR(20) DEFAULT 'UN',
                data_primeiro_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_ultimo_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                total_registros INT DEFAULT 1,
                INDEX idx_partnumber (partnumber)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'depositos_registrados' => "CREATE TABLE IF NOT EXISTS depositos_registrados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                deposito VARCHAR(100) UNIQUE NOT NULL,
                localizacao VARCHAR(100),
                data_primeiro_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_ultimo_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                total_registros INT DEFAULT 1,
                INDEX idx_deposito (deposito)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'notificacoes_admin' => "CREATE TABLE IF NOT EXISTS notificacoes_admin (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inventario_id INT NOT NULL,
                usuario_nome VARCHAR(100) NOT NULL,
                partnumber VARCHAR(100) NOT NULL,
                deposito VARCHAR(100) NOT NULL,
                fase TINYINT DEFAULT 1 COMMENT '1=primaria,2=secundaria,3=terceira',
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_inventario_criado (inventario_id, criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($tables as $tblName => $sql) {
            if (!$conn->query($sql)) {
                throw new Exception("Erro ao criar tabela {$tblName}: " . $conn->error);
            }
        }
    }

    private static function seedDefaultUsers(mysqli $conn): void
    {
        $conn->query("INSERT IGNORE INTO usuarios (nome, matricula, tipo, email)
            VALUES ('Administrador Sistema', 'admin', 'admin', 'admin@empresa.com')");

        $conn->query("INSERT IGNORE INTO usuarios (nome, matricula, tipo, email)
            VALUES ('Operador Padrão', 'operador', 'operador', 'operador@empresa.com')");
    }

    private static function runAlterations(mysqli $conn): void
    {
        // Campos para controle de múltiplas contagens e finalização
        self::addColumnIfNotExists($conn, 'contagens', 'finalizado',                   'BOOLEAN DEFAULT FALSE');
        self::addColumnIfNotExists($conn, 'contagens', 'numero_contagens_realizadas',  'TINYINT DEFAULT 1');
        self::addColumnIfNotExists($conn, 'contagens', 'pode_nova_contagem',           'BOOLEAN DEFAULT TRUE');
        self::addColumnIfNotExists($conn, 'contagens', 'data_finalizacao',             'TIMESTAMP NULL');
    }

    private static function addColumnIfNotExists(
        mysqli $conn,
        string $table,
        string $column,
        string $definition
    ): void {
        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}