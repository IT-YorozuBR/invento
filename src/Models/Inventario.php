<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Inventario
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAtivo(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, u.nome AS admin_nome
             FROM inventarios i
             LEFT JOIN usuarios u ON i.admin_id = u.id
             WHERE i.status = 'aberto'
             ORDER BY i.data_inicio DESC
             LIMIT 1"
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, u.nome AS admin_nome
             FROM inventarios i
             LEFT JOIN usuarios u ON i.admin_id = u.id
             WHERE i.id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function findConcluidos(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT SQL_CALC_FOUND_ROWS i.*, u.nome AS admin_nome
             FROM inventarios i
             LEFT JOIN usuarios u ON i.admin_id = u.id
             WHERE i.status IN ('fechado','cancelado')
             ORDER BY i.data_fim DESC, i.data_inicio DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();

        $inventarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total = (int) $this->db->query('SELECT FOUND_ROWS() AS total')
            ->fetch_assoc()['total'];

        return [
            'items'       => $inventarios,
            'page'        => $page,
            'total_pages' => (int) ceil($total / $perPage),
            'total'       => $total,
        ];
    }

    public function create(string $dataInicio, string $descricao, int $adminId): array
    {
        $codigo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        // Fechar inventários abertos
        $this->db->query("UPDATE inventarios SET status = 'fechado' WHERE status = 'aberto'");

        $stmt = $this->db->prepare(
            "INSERT INTO inventarios (codigo, data_inicio, descricao, admin_id, status)
             VALUES (?, ?, ?, ?, 'aberto')"
        );
        $stmt->bind_param('sssi', $codigo, $dataInicio, $descricao, $adminId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Inventário criado com sucesso!',
                    'codigo' => $codigo, 'id' => $this->db->lastInsertId()];
        }

        return ['success' => false, 'message' => 'Erro ao criar inventário'];
    }

    public function fechar(int $id): array
    {
        $stmt = $this->db->prepare(
            "UPDATE inventarios SET status = 'fechado', data_fim = CURDATE() WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Inventário fechado com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao fechar inventário'];
    }

    public function getEstatisticas(int $inventarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*)                                                     AS total,
                SUM(status = 'concluida')                                    AS concluidas,
                SUM(status = 'divergente')                                   AS divergentes,
                SUM(status = 'primaria')                                     AS pendentes,
                SUM(status = 'terceira')                                     AS terceiras,
                COUNT(DISTINCT partnumber)                                   AS partnumbers,
                SUM(COALESCE(quantidade_final, quantidade_primaria))         AS qtd_total
             FROM contagens
             WHERE inventario_id = ?"
        );
        $stmt->bind_param('i', $inventarioId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return [
            'total'          => (int) ($row['total'] ?? 0),
            'concluidas'     => (int) ($row['concluidas'] ?? 0),
            'divergentes'    => (int) ($row['divergentes'] ?? 0),
            'pendentes'      => (int) ($row['pendentes'] ?? 0),
            'terceiras'      => (int) ($row['terceiras'] ?? 0),
            'partnumbers'    => (int) ($row['partnumbers'] ?? 0),
            'qtd_total'      => (float) ($row['qtd_total'] ?? 0),
        ];
    }
}
