<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Usuario
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByMatricula(string $matricula, string $tipo = ''): ?array
    {
        $sql    = 'SELECT id, nome, matricula, tipo FROM usuarios WHERE matricula = ? AND ativo = 1';
        $params = [$matricula];
        $types  = 's';

        if (!empty($tipo)) {
            $sql    .= ' AND tipo = ?';
            $params[] = $tipo;
            $types  .= 's';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function findOrCreate(string $nome, string $matricula, string $tipo = 'operador'): ?int
    {
        $existing = $this->findByMatricula($matricula);
        if ($existing) {
            $this->updateLastLogin($existing['id']);
            return $existing['id'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (nome, matricula, tipo, ativo) VALUES (?, ?, ?, 1)"
        );
        $stmt->bind_param('sss', $nome, $matricula, $tipo);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }

        return null;
    }

    private function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
}
