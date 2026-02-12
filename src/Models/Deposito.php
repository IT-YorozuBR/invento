<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Deposito
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        $stmt = $this->db->prepare(
            'SELECT deposito, localizacao, total_registros FROM depositos_registrados ORDER BY deposito ASC'
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function suggest(string $term): array
    {
        $like = '%' . $this->db->escape($term) . '%';
        $stmt = $this->db->prepare(
            'SELECT deposito, localizacao FROM depositos_registrados
             WHERE deposito LIKE ? ORDER BY total_registros DESC, data_ultimo_registro DESC LIMIT 10'
        );
        $stmt->bind_param('s', $like);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function save(string $deposito, string $localizacao = ''): array
    {
        if (!$this->validate($deposito)) {
            return ['success' => false, 'message' => 'Nome do depósito inválido'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO depositos_registrados (deposito, localizacao)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                localizacao     = COALESCE(?, localizacao),
                total_registros = total_registros + 1,
                data_ultimo_registro = NOW()'
        );
        $stmt->bind_param('sss', $deposito, $localizacao, $localizacao);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Depósito salvo com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao salvar depósito'];
    }

    public function delete(string $deposito): array
    {
        $stmt = $this->db->prepare('DELETE FROM depositos_registrados WHERE deposito = ?');
        $stmt->bind_param('s', $deposito);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Depósito excluído com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao excluir depósito'];
    }

    public function touch(string $deposito, string $localizacao = ''): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO depositos_registrados (deposito, localizacao)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                total_registros      = total_registros + 1,
                data_ultimo_registro = NOW()'
        );
        $stmt->bind_param('ss', $deposito, $localizacao);
        $stmt->execute();
    }

    private function validate(string $deposito): bool
    {
        $len = mb_strlen($deposito, 'UTF-8');
        return $len >= 2 && $len <= 100;
    }
}
