<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Contagem
{
    private Database $db;
    private const TOLERANCE_PERCENT = 10;
    private const MAX_CONTAGENS = 3;

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
            $nova = $existing['quantidade_primaria'] + $quantidade;
            $id   = $existing['id'];

            $stmt = $this->db->prepare(
                'UPDATE contagens SET quantidade_primaria = ?, data_contagem_primaria = NOW() WHERE id = ?'
            );
            $stmt->bind_param('di', $nova, $id);
            $stmt->execute();

            return [
                'success' => true,
                'tipo' => 'atualizacao',
                'message' => 'Contagem atualizada com sucesso!',
                'quantidade_nova' => $nova
            ];
        }

        $descricao = $extra['descricao'] ?? null;
        $unidade   = $extra['unidade'] ?? 'UN';
        $validade  = $extra['validade'] ?? null;

        $stmt = $this->db->prepare(
            "INSERT INTO contagens
                (inventario_id, usuario_id, deposito, partnumber, quantidade_primaria,
                 descricao_item, unidade_medida, lote, validade, status,
                 numero_contagens_realizadas, pode_nova_contagem, finalizado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'primaria', 1, 1, 0)"
        );
        $stmt->bind_param(
            'iissdssss',
            $inventarioId,
            $usuarioId,
            $deposito,
            $partnumber,
            $quantidade,
            $descricao,
            $unidade,
            $lote,
            $validade
        );

        if ($stmt->execute()) {
            return ['success' => true, 'tipo' => 'nova', 'message' => 'Contagem registrada com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao registrar contagem'];
    }

    /**
     * Verifica se um partnumber/deposito está finalizado no inventário.
     * @return array{finalizado: bool, existe: bool, id: int, status: string}
     */
    public function verificarPartNumberFinalizado(int $inventarioId, string $partnumber, string $deposito): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, status, finalizado FROM contagens
             WHERE inventario_id = ? AND partnumber = ? AND deposito = ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->bind_param('iss', $inventarioId, $partnumber, $deposito);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return ['finalizado' => false, 'existe' => false, 'id' => 0, 'status' => ''];
        }

        return [
            'finalizado' => (bool) $row['finalizado'],
            'existe'     => true,
            'id'         => (int) $row['id'],
            'status'     => $row['status'],
        ];
    }

    /**
     * Verifica se é possível iniciar nova contagem para o registro.
     * @return array{pode: bool, numero_proxima_contagem: int, mensagem: string}
     */
    public function podeIniciarNovaContagem(int $contagemId): array
    {
        $row = $this->findById($contagemId);

        if (!$row) {
            return ['pode' => false, 'numero_proxima_contagem' => 0, 'mensagem' => 'Contagem não encontrada.'];
        }

        if ((bool) $row['finalizado']) {
            return ['pode' => false, 'numero_proxima_contagem' => 0, 'mensagem' => 'Esta contagem já foi finalizada.'];
        }

        $numContagens = (int) ($row['numero_contagens_realizadas'] ?? 1);

        if ($numContagens >= self::MAX_CONTAGENS) {
            return [
                'pode'                    => false,
                'numero_proxima_contagem' => 0,
                'mensagem'                => 'Número máximo de contagens (3) já atingido.',
            ];
        }

        if (!in_array($row['status'], ['primaria', 'divergente'], true)) {
            return [
                'pode'                    => false,
                'numero_proxima_contagem' => 0,
                'mensagem'                => 'Status atual não permite nova contagem.',
            ];
        }

        return [
            'pode'                    => true,
            'numero_proxima_contagem' => $numContagens + 1,
            'mensagem'                => '',
        ];
    }

    /**
     * Registra nova contagem (2ª ou 3ª), despachando para o método correto.
     */
    public function registrarNovaContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada.'];
        }

        $numContagens = (int) ($row['numero_contagens_realizadas'] ?? 1);

        if ($numContagens === 1) {
            return $this->registrarSegundaContagemManual($contagemId, $quantidade, $usuarioId);
        }

        if ($numContagens === 2) {
            return $this->registrarTerceiraContagemManual($contagemId, $quantidade, $usuarioId);
        }

        return ['success' => false, 'message' => 'Número máximo de contagens já atingido.'];
    }

    public function findOpenByPartnumber(int $inventarioId, string $partnumber, string $deposito): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM contagens
         WHERE inventario_id = ? AND partnumber = ? AND deposito = ? AND finalizado = 0
         ORDER BY id DESC LIMIT 1'
        );
        $stmt->bind_param('iss', $inventarioId, $partnumber, $deposito);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    /**
     * Registra 2ª contagem com comparação automática.
     */
    public function registrarSegundaContagemManual(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada'];
        }

        $primaria   = (float) $row['quantidade_primaria'];
        $diferenca  = abs($primaria - $quantidade);
        $percentual = ($diferenca / max($primaria, $quantidade, 1)) * 100;

        if ($primaria == $quantidade) {
            $status   = 'concluida';
            $final    = $quantidade;
            $mensagem = "✔ Contagens coincidem! Quantidade: " . number_format($final, 2, ',', '.');

            $stmt = $this->db->prepare(
                'UPDATE contagens
                 SET quantidade_secundaria = ?, usuario_secundario_id = ?,
                     data_contagem_secundaria = NOW(), status = ?,
                     quantidade_final = ?, numero_contagens_realizadas = 2
                 WHERE id = ?'
            );
            $stmt->bind_param('disdi', $quantidade, $usuarioId, $status, $final, $contagemId);
            $stmt->execute();

            return ['success' => true, 'message' => $mensagem, 'status' => $status, 'divergencia' => 0];
        } elseif ($percentual <= self::TOLERANCE_PERCENT) {
            $status = 'concluida';
            $final  = round(($primaria + $quantidade) / 2, 4);
            $mensagem = sprintf(
                '✔ Pequena divergência (%.1f%%). Média utilizada: %s',
                $percentual,
                number_format($final, 2, ',', '.')
            );

            $stmt = $this->db->prepare(
                'UPDATE contagens
                 SET quantidade_secundaria = ?, usuario_secundario_id = ?,
                     data_contagem_secundaria = NOW(), status = ?,
                     quantidade_final = ?, numero_contagens_realizadas = 2
                 WHERE id = ?'
            );
            $stmt->bind_param('disdi', $quantidade, $usuarioId, $status, $final, $contagemId);
            $stmt->execute();

            return ['success' => true, 'message' => $mensagem, 'status' => $status, 'divergencia' => $percentual];
        } else {
            $status   = 'divergente';
            $mensagem = sprintf(
                '⚠ Divergência significativa (%.1f%%). Necessária terceira contagem.',
                $percentual
            );

            $stmt = $this->db->prepare(
                'UPDATE contagens
                 SET quantidade_secundaria = ?, usuario_secundario_id = ?,
                     data_contagem_secundaria = NOW(), status = ?,
                     numero_contagens_realizadas = 2
                 WHERE id = ?'
            );
            $stmt->bind_param('disi', $quantidade, $usuarioId, $status, $contagemId);
            $stmt->execute();

            return ['success' => true, 'message' => $mensagem, 'status' => $status, 'divergencia' => $percentual];
        }
    }

    /**
     * Registra 3ª contagem usando mediana ou valor que aparece 2 vezes.
     */
    public function registrarTerceiraContagemManual(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada'];
        }

        $primaria   = (float) $row['quantidade_primaria'];
        $secundaria = (float) $row['quantidade_secundaria'];
        $contagens  = [$primaria, $secundaria, $quantidade];

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
            $final = $sorted[1];
        }

        $status   = 'concluida';
        $mensagem = "✔ Terceira contagem concluída! Quantidade final definida: " . number_format($final, 2, ',', '.');

        $stmt = $this->db->prepare(
            'UPDATE contagens
             SET quantidade_terceira = ?, usuario_terceiro_id = ?,
                 quantidade_final = ?, data_contagem_terceira = NOW(),
                 status = ?, numero_contagens_realizadas = 3
             WHERE id = ?'
        );
        $stmt->bind_param('didsi', $quantidade, $usuarioId, $final, $status, $contagemId);

        if ($stmt->execute()) {
            return [
                'success'          => true,
                'message'          => $mensagem,
                'status'           => $status,
                'quantidade_final' => $final,
            ];
        }

        return ['success' => false, 'message' => 'Erro ao registrar terceira contagem'];
    }

    /**
     * Finaliza a contagem, bloqueando novas contagens.
     * O admin pode encerrar qualquer status (inclusive 'primaria' e 'divergente').
     */
    public function finalizarContagem(int $contagemId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada.'];
        }

        if ((bool) $row['finalizado']) {
            return ['success' => false, 'message' => 'Esta contagem já foi encerrada.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE contagens
             SET finalizado = 1, pode_nova_contagem = 0, data_finalizacao = NOW()
             WHERE id = ?'
        );
        $stmt->bind_param('i', $contagemId);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Contagem encerrada! O partnumber "' . ($row['partnumber'] ?? '') . '" não pode mais ser contado.',
            ];
        }

        return ['success' => false, 'message' => 'Erro ao encerrar contagem.'];
    }

    /**
     * Exporta dados agrupados por partnumber (consolidado por todos os depósitos).
     */
    public function exportarDadosConsolidados(int $inventarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                c.partnumber,
                c.descricao_item,
                c.unidade_medida,
                SUM(COALESCE(c.quantidade_final, c.quantidade_primaria)) AS quantidade_total,
                COUNT(c.id) AS num_depositos,
                GROUP_CONCAT(
                    CONCAT(c.deposito, ': ',
                           COALESCE(c.quantidade_final, c.quantidade_primaria))
                    ORDER BY c.deposito
                    SEPARATOR ' | '
                ) AS detalhes_depositos
             FROM contagens c
             WHERE c.inventario_id = ?
             GROUP BY c.partnumber, c.descricao_item, c.unidade_medida
             ORDER BY c.partnumber"
        );
        $stmt->bind_param('i', $inventarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // -----------------------------------------------------------------------
    // Métodos originais mantidos para compatibilidade
    // -----------------------------------------------------------------------

    public function registrarSegundaContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada'];
        }

        if ($row['quantidade_secundaria'] !== null) {
            return $this->registrarTerceiraContagemManual($contagemId, $quantidade, $usuarioId);
        }

        return $this->registrarSegundaContagemManual($contagemId, $quantidade, $usuarioId);
    }

    public function registrarTerceiraContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        return $this->registrarTerceiraContagemManual($contagemId, $quantidade, $usuarioId);
    }

    public function exportarDados(int $inventarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                c.deposito, c.partnumber, c.descricao_item, c.unidade_medida,
                c.lote, c.validade,
                c.quantidade_primaria, c.quantidade_secundaria,
                c.quantidade_terceira, c.quantidade_final,
                c.status, c.finalizado, c.data_finalizacao,
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
