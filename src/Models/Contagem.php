<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Contagem
{
    private Database $db;
    private const TOLERANCE_PERCENT = 10;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contagens WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function findPaginated(int $inventarioId, int $page = 1, array $filters = []): array
    {
        $perPage = (int) ($_ENV['ITEMS_PER_PAGE'] ?? 20);
        $offset  = ($page - 1) * $perPage;

        $sql    = "SELECT SQL_CALC_FOUND_ROWS
                       c.*,
                       u1.nome  AS usuario_nome,
                       u2.nome  AS usuario_secundario_nome,
                       u3.nome  AS usuario_terceiro_nome
                   FROM contagens c
                   JOIN  usuarios u1 ON c.usuario_id            = u1.id
                   LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
                   LEFT JOIN usuarios u3 ON c.usuario_terceiro_id   = u3.id
                   WHERE c.inventario_id = ?";
        $params = [$inventarioId];
        $types  = 'i';

        if (!empty($filters['status'])) {
            $sql    .= ' AND c.status = ?';
            $params[] = $filters['status'];
            $types  .= 's';
        }
        if (!empty($filters['partnumber'])) {
            $sql    .= ' AND c.partnumber LIKE ?';
            $params[] = '%' . $filters['partnumber'] . '%';
            $types  .= 's';
        }
        if (!empty($filters['deposito'])) {
            $sql    .= ' AND c.deposito LIKE ?';
            $params[] = '%' . $filters['deposito'] . '%';
            $types  .= 's';
        }

        $sql    .= ' ORDER BY c.data_contagem_primaria DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;
        $types  .= 'ii';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $total = (int) $this->db->query('SELECT FOUND_ROWS() AS total')->fetch_assoc()['total'];

        return [
            'items'       => $items,
            'page'        => $page,
            'total_pages' => (int) ceil($total / $perPage),
            'total'       => $total,
        ];
    }

    public function registrarPrimaria(
        int    $inventarioId,
        int    $usuarioId,
        string $deposito,
        string $partnumber,
        float  $quantidade,
        array  $extra = []
    ): array {
        // Verificar contagem existente para esse depósito/partnumber/lote
        $lote = $extra['lote'] ?? null;

        $sql    = 'SELECT id, quantidade_primaria FROM contagens
                   WHERE inventario_id = ? AND deposito = ? AND partnumber = ?';
        $params = [$inventarioId, $deposito, $partnumber];
        $types  = 'iss';

        if ($lote !== null) {
            $sql    .= ' AND lote = ?';
            $params[] = $lote;
            $types  .= 's';
        } else {
            $sql .= ' AND lote IS NULL';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            // Somar à contagem primária existente
            $nova = $existing['quantidade_primaria'] + $quantidade;
            $id   = $existing['id'];

            $stmt = $this->db->prepare(
                'UPDATE contagens SET quantidade_primaria = ?, data_contagem_primaria = NOW() WHERE id = ?'
            );
            $stmt->bind_param('di', $nova, $id);
            $stmt->execute();

            return ['success' => true, 'tipo' => 'atualizacao',
                    'message' => 'Contagem atualizada com sucesso!',
                    'quantidade_nova' => $nova];
        }

        // Nova contagem
        $descricao = $extra['descricao'] ?? null;
        $unidade   = $extra['unidade'] ?? 'UN';
        $validade  = $extra['validade'] ?? null;

        $stmt = $this->db->prepare(
            "INSERT INTO contagens
                (inventario_id, usuario_id, deposito, partnumber, quantidade_primaria,
                 descricao_item, unidade_medida, lote, validade, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'primaria')"
        );
        $stmt->bind_param('iissdssss',
            $inventarioId, $usuarioId, $deposito, $partnumber, $quantidade,
            $descricao, $unidade, $lote, $validade
        );

        if ($stmt->execute()) {
            return ['success' => true, 'tipo' => 'nova', 'message' => 'Contagem registrada com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao registrar contagem'];
    }

    public function registrarSegundaContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada'];
        }

        // Se já tem segunda contagem, vai para terceira
        if ($row['quantidade_secundaria'] !== null) {
            return $this->registrarTerceiraContagem($contagemId, $quantidade, $usuarioId);
        }

        $primaria    = (float) $row['quantidade_primaria'];
        $diferenca   = abs($primaria - $quantidade);
        $percentual  = ($diferenca / max($primaria, $quantidade, 1)) * 100;

        if ($primaria == $quantidade) {
            $status    = 'concluida';
            $mensagem  = 'Contagens coincidem. Concluído com sucesso!';
        } elseif ($percentual <= self::TOLERANCE_PERCENT) {
            $status    = 'concluida';
            $mensagem  = sprintf(
                'Pequena divergência (%.1f%%). Média utilizada como quantidade final.',
                $percentual
            );
            $final     = round(($primaria + $quantidade) / 2);
            $stmt = $this->db->prepare(
                'UPDATE contagens SET quantidade_secundaria = ?, usuario_secundario_id = ?,
                 data_contagem_secundaria = NOW(), status = ?, quantidade_final = ? WHERE id = ?'
            );
            $stmt->bind_param('disdi', $quantidade, $usuarioId, $status, $final, $contagemId);
            $stmt->execute();
            return ['success' => true, 'message' => $mensagem, 'status' => $status, 'divergencia' => $percentual];
        } else {
            $status   = 'divergente';
            $mensagem = sprintf(
                'Divergência significativa (%.1f%%). Necessária terceira contagem.',
                $percentual
            );
        }

        $stmt = $this->db->prepare(
            'UPDATE contagens SET quantidade_secundaria = ?, usuario_secundario_id = ?,
             data_contagem_secundaria = NOW(), status = ? WHERE id = ?'
        );
        $stmt->bind_param('disi', $quantidade, $usuarioId, $status, $contagemId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => $mensagem, 'status' => $status, 'divergencia' => $percentual];
        }

        return ['success' => false, 'message' => 'Erro ao registrar segunda contagem'];
    }

    public function registrarTerceiraContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada'];
        }

        $primaria   = (float) $row['quantidade_primaria'];
        $secundaria = (float) $row['quantidade_secundaria'];
        $contagens  = [$primaria, $secundaria, $quantidade];

        // Duas iguais → usar esse valor; todas diferentes → usar mediana
        $final = null;
        foreach (array_count_values(array_map('intval', $contagens)) as $valor => $count) {
            if ($count >= 2) {
                $final = (float) $valor;
                break;
            }
        }
        if ($final === null) {
            $sorted = $contagens;
            sort($sorted);
            $final = $sorted[1]; // mediana
        }

        $status = 'concluida';
        $stmt   = $this->db->prepare(
            'UPDATE contagens SET
                quantidade_terceira = ?, usuario_terceiro_id = ?,
                quantidade_final = ?, data_contagem_terceira = NOW(),
                status = ? WHERE id = ?'
        );
        $stmt->bind_param('didsi', $quantidade, $usuarioId, $final, $status, $contagemId);

        if ($stmt->execute()) {
            return [
                'success'          => true,
                'message'          => "Terceira contagem registrada! Quantidade final: {$final}",
                'status'           => $status,
                'quantidade_final' => $final,
            ];
        }

        return ['success' => false, 'message' => 'Erro ao registrar terceira contagem'];
    }

    public function exportarDados(int $inventarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                c.deposito, c.partnumber, c.descricao_item, c.unidade_medida,
                c.lote, c.validade,
                c.quantidade_primaria, c.quantidade_secundaria,
                c.quantidade_terceira, c.quantidade_final,
                c.status,
                u1.nome AS contador_1, u2.nome AS contador_2, u3.nome AS contador_3,
                c.data_contagem_primaria, c.data_contagem_secundaria,
                c.data_contagem_terceira, c.observacoes
             FROM contagens c
             LEFT JOIN usuarios u1 ON c.usuario_id             = u1.id
             LEFT JOIN usuarios u2 ON c.usuario_secundario_id  = u2.id
             LEFT JOIN usuarios u3 ON c.usuario_terceiro_id    = u3.id
             WHERE c.inventario_id = ?
             ORDER BY c.deposito, c.partnumber"
        );
        $stmt->bind_param('i', $inventarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
