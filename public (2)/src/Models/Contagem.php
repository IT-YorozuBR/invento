<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Modelo de Contagem - NOVA LÓGICA COM CONTROLE POR ADMIN
 * 
 * FLUXO:
 * 1. Operário conta → Registra/Soma na fase atual
 * 2. Admin libera próxima fase → Operário pode avançar
 * 3. Repete até 3ª contagem
 * 4. 3ª contagem verifica convergência e finaliza automaticamente
 */
class Contagem
{
    private Database $db;
    private const MAX_CONTAGENS = 3;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =======================================================================
    // MÉTODOS PARA ADMIN LIBERAR FASES
    // =======================================================================

    /**
     * ADMIN: Libera segunda contagem para um registro específico
     */
    public function iniciarSegundaContagem(int $contagemId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada.'];
        }

        if ((int)$row['numero_contagens_realizadas'] !== 1) {
            return ['success' => false, 'message' => 'Só pode iniciar segunda contagem após primeira estar completa.'];
        }

        if ((bool)$row['finalizado']) {
            return ['success' => false, 'message' => 'Contagem já finalizada.'];
        }

        $stmt = $this->db->prepare('UPDATE contagens SET pode_nova_contagem = 1 WHERE id = ?');
        $stmt->bind_param('i', $contagemId);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => '✅ Segunda contagem liberada! O operador pode realizar a contagem agora.',
                'partnumber' => $row['partnumber'],
                'deposito' => $row['deposito']
            ];
        }

        return ['success' => false, 'message' => 'Erro ao liberar segunda contagem.'];
    }

    /**
     * ADMIN: Libera terceira contagem para um registro específico
     */
    public function iniciarTerceiraContagem(int $contagemId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada.'];
        }

        if ((int)$row['numero_contagens_realizadas'] !== 2) {
            return ['success' => false, 'message' => 'Só pode iniciar terceira contagem após segunda estar completa.'];
        }

        if ((bool)$row['finalizado']) {
            return ['success' => false, 'message' => 'Contagem já finalizada.'];
        }

        $stmt = $this->db->prepare('UPDATE contagens SET pode_nova_contagem = 1 WHERE id = ?');
        $stmt->bind_param('i', $contagemId);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => '✅ Terceira contagem liberada! O operador pode realizar a contagem final agora.',
                'partnumber' => $row['partnumber'],
                'deposito' => $row['deposito']
            ];
        }

        return ['success' => false, 'message' => 'Erro ao liberar terceira contagem.'];
    }

    // =======================================================================
    // MÉTODOS PARA OPERÁRIO REGISTRAR CONTAGENS
    // =======================================================================

    /**
     * Operário registra contagem
     * LÓGICA:
     * - Se não existe: cria primeira contagem (pode_nova_contagem = FALSE)
     * - Se existe e pode_nova_contagem = FALSE: SOMA na contagem atual
     * - Se existe e pode_nova_contagem = TRUE: avança para próxima fase
     */
    public function registrarPrimaria(
        int    $inventarioId,
        int    $usuarioId,
        string $deposito,
        string $partnumber,
        float  $quantidade,
        array  $extra = []
    ): array {
        $lote = $extra['lote'] ?? null;

        // Busca contagem existente
        $sql    = 'SELECT * FROM contagens
                   WHERE inventario_id = ? AND deposito = ? AND partnumber = ?';
        $params = [$inventarioId, $deposito, $partnumber];
        $types  = 'iss';

        if ($lote !== null) {
            $sql    .= ' AND lote = ?';
            $params[] = $lote;
            $types  .= 's';
        } else {
            $sql .= ' AND (lote IS NULL OR lote = "")';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        // Se já existe contagem
        if ($existing) {
            $podeNova  = (bool) $existing['pode_nova_contagem'];
            $finalizado = (bool) $existing['finalizado'];

            if ($finalizado) {
                return ['success' => false, 'message' => 'Esta contagem já foi finalizada.'];
            }

            // Se ADMIN LIBEROU próxima fase
            if ($podeNova) {
                return $this->avancarParaProximaFase($existing, $quantidade, $usuarioId);
            }

            // Se NÃO liberou, SOMA na fase atual — passa $existing para evitar findById() extra
            return $this->somarNaFaseAtual($existing, $quantidade);
        }

        // Cria PRIMEIRA contagem (nova)
        return $this->criarPrimeiraContagem($inventarioId, $usuarioId, $deposito, $partnumber, $quantidade, $extra);
    }

    /**
     * SOMA na fase atual (quando admin não liberou próxima fase)
     * Recebe $row já carregado para evitar query duplicada.
     */
    private function somarNaFaseAtual(array $row, float $quantidade): array
    {
        $contagemId   = (int) $row['id'];
        $numContagens = (int) $row['numero_contagens_realizadas'];

        if ($numContagens === 1) {
            // SOMA na primária
            $nova = (float)$row['quantidade_primaria'] + $quantidade;
            
            $stmt = $this->db->prepare(
                'UPDATE contagens SET quantidade_primaria = ?, data_contagem_primaria = NOW(), status = "primaria" WHERE id = ?'
            );
            $stmt->bind_param('di', $nova, $contagemId);
            $stmt->execute();

            return [
                'success' => true,
                'fase'    => 1,
                'message' => '✔ Somado à primeira contagem! Total: ' . number_format($nova, 2, ',', '.') .
                             ' un (Aguardando admin liberar segunda contagem)',
            ];

        } elseif ($numContagens === 2) {
            // SOMA na secundária
            $nova = (float)$row['quantidade_secundaria'] + $quantidade;

            $stmt = $this->db->prepare(
                'UPDATE contagens SET quantidade_secundaria = ?, data_contagem_secundaria = NOW(), status = "secundaria" WHERE id = ?'
            );
            $stmt->bind_param('di', $nova, $contagemId);
            $stmt->execute();

            return [
                'success' => true,
                'fase'    => 2,
                'message' => '✔ Somado à contagem! Total: ' . number_format($nova, 2, ',', '.'),
            ];

        } elseif ($numContagens === 3) {
            return ['success' => false, 'message' => 'Contagem já possui 3 registros.'];
        }

        return ['success' => false, 'message' => 'Estado inválido da contagem.'];
    }

    /**
     * Avança para próxima fase (quando admin liberou)
     * Recebe $row já carregado para evitar query duplicada.
     */
    private function avancarParaProximaFase(array $row, float $quantidade, int $usuarioId): array
    {
        $contagemId   = (int) $row['id'];
        $numContagens = (int) $row['numero_contagens_realizadas'];

        if ($numContagens === 1) {
            return $this->registrarSegundaFase($contagemId, $quantidade, $usuarioId);
        } elseif ($numContagens === 2) {
            return $this->registrarTerceiraFase($row, $quantidade, $usuarioId);
        }

        return ['success' => false, 'message' => 'Número máximo de contagens atingido'];
    }

    /**
     * Registra SEGUNDA FASE (admin já liberou)
     */
    private function registrarSegundaFase(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'UPDATE contagens
             SET quantidade_secundaria = ?, usuario_secundario_id = ?,
                 data_contagem_secundaria = NOW(), status = "secundaria",
                 numero_contagens_realizadas = 2, pode_nova_contagem = 0
             WHERE id = ?'
        );
        $stmt->bind_param('dii', $quantidade, $usuarioId, $contagemId);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'fase'    => 2,
                'message' => '✔ Contagem registrada! Quantidade: ' . number_format($quantidade, 2, ',', '.'),
            ];
        }

        return ['success' => false, 'message' => 'Erro ao registrar segunda contagem'];
    }

    /**
     * Registra TERCEIRA FASE e verifica CONVERGÊNCIA
     * Recebe $row já carregado para evitar query duplicada.
     */
    private function registrarTerceiraFase(array $row, float $quantidade, int $usuarioId): array
    {
        $contagemId = (int) $row['id'];
        
        $primaria   = (float)$row['quantidade_primaria'];
        $secundaria = (float)$row['quantidade_secundaria'];
        $terceira   = $quantidade;

        // Verifica CONVERGÊNCIA
        $quantidadeFinal = null;
        $status = null;
        $mensagem = '';

        if ($primaria == $secundaria || $primaria == $terceira) {
            $quantidadeFinal = $primaria;
            $status = 'concluida';
            $mensagem = "✅ CONVERGENTE! Quantidade final: " . number_format($quantidadeFinal, 2, ',', '.') . 
                       " un (1ª: " . number_format($primaria, 2, ',', '.') . 
                       ", 2ª: " . number_format($secundaria, 2, ',', '.') . 
                       ", 3ª: " . number_format($terceira, 2, ',', '.') . ")";

        } elseif ($secundaria == $terceira) {
            $quantidadeFinal = $secundaria;
            $status = 'concluida';
            $mensagem = "✅ CONVERGENTE! Quantidade final: " . number_format($quantidadeFinal, 2, ',', '.') . 
                       " un (1ª: " . number_format($primaria, 2, ',', '.') . 
                       ", 2ª: " . number_format($secundaria, 2, ',', '.') . 
                       ", 3ª: " . number_format($terceira, 2, ',', '.') . ")";

        } else {
            $status = 'divergente';
            $quantidadeFinal = null;
            $mensagem = "⚠️ DIVERGENTE! Todas as contagens diferentes. Contagem encerrada SEM quantidade final. " .
                       "(1ª: " . number_format($primaria, 2, ',', '.') . 
                       ", 2ª: " . number_format($secundaria, 2, ',', '.') . 
                       ", 3ª: " . number_format($terceira, 2, ',', '.') . ")";
        }

        // Atualiza com finalização
        if ($quantidadeFinal !== null) {
            $stmt = $this->db->prepare(
                'UPDATE contagens
                 SET quantidade_terceira = ?, usuario_terceiro_id = ?, quantidade_final = ?,
                     data_contagem_terceira = NOW(), status = ?, numero_contagens_realizadas = 3,
                     finalizado = 1, pode_nova_contagem = 0, data_finalizacao = NOW()
                 WHERE id = ?'
            );
            $stmt->bind_param('didsi', $terceira, $usuarioId, $quantidadeFinal, $status, $contagemId);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE contagens
                 SET quantidade_terceira = ?, usuario_terceiro_id = ?,
                     data_contagem_terceira = NOW(), status = ?, numero_contagens_realizadas = 3,
                     finalizado = 1, pode_nova_contagem = 0, data_finalizacao = NOW()
                 WHERE id = ?'
            );
            $stmt->bind_param('disi', $terceira, $usuarioId, $status, $contagemId);
        }

        if ($stmt->execute()) {
            return [
                'success'          => true,
                'fase'             => 3,
                'message'          => $mensagem,
                'status'           => $status,
                'quantidade_final' => $quantidadeFinal,
                'convergente'      => ($quantidadeFinal !== null),
            ];
        }

        return ['success' => false, 'message' => 'Erro ao registrar terceira contagem'];
    }

    /**
     * Cria primeira contagem (nova)
     */
    private function criarPrimeiraContagem(
        int $inventarioId,
        int $usuarioId,
        string $deposito,
        string $partnumber,
        float $quantidade,
        array $extra
    ): array {
        $descricao = $extra['descricao'] ?? null;
        $unidade   = $extra['unidade'] ?? 'UN';
        $lote      = $extra['lote'] ?? null;
        $validade  = $extra['validade'] ?? null;

        $stmt = $this->db->prepare(
            "INSERT INTO contagens
                (inventario_id, usuario_id, deposito, partnumber, quantidade_primaria,
                 descricao_item, unidade_medida, lote, validade, status,
                 numero_contagens_realizadas, pode_nova_contagem, finalizado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'primaria', 1, 0, 0)"
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
            return [
                'success' => true,
                'fase'    => 1,
                'message' => '✔ Contagem registrada! Quantidade: ' . number_format($quantidade, 2, ',', '.'),
            ];
        }

        return ['success' => false, 'message' => 'Erro ao registrar contagem'];
    }

    // =======================================================================
    // MÉTODOS AUXILIARES E CONSULTAS
    // =======================================================================

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contagens WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    public function findPaginated(int $inventarioId, int $page = 1, array $filters = []): array
    {
        $perPage = (int) ($_ENV['ITEMS_PER_PAGE'] ?? 20);
        $offset  = ($page - 1) * $perPage;

        // ── Monta cláusulas de filtro compartilhadas entre COUNT e SELECT ──
        $where  = 'c.inventario_id = ?';
        $params = [$inventarioId];
        $types  = 'i';

        if (!empty($filters['status'])) {
            $where   .= ' AND c.status = ?';
            $params[] = $filters['status'];
            $types   .= 's';
        }
        if (!empty($filters['partnumber'])) {
            $where   .= ' AND c.partnumber LIKE ?';
            $params[] = '%' . $filters['partnumber'] . '%';
            $types   .= 's';
        }
        if (!empty($filters['deposito'])) {
            $where   .= ' AND c.deposito LIKE ?';
            $params[] = '%' . $filters['deposito'] . '%';
            $types   .= 's';
        }

        // ── COUNT separado (substitui SQL_CALC_FOUND_ROWS, deprecated no MySQL 8+) ──
        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM contagens c WHERE {$where}");
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = (int) $countStmt->get_result()->fetch_assoc()['total'];

        // ── SELECT paginado ──
        $sql = "SELECT
                       c.*,
                       u1.nome AS usuario_nome,
                       u2.nome AS usuario_secundario_nome,
                       u3.nome AS usuario_terceiro_nome
                   FROM contagens c
                   JOIN  usuarios u1 ON c.usuario_id = u1.id
                   LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
                   LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
                   WHERE {$where}
                   ORDER BY c.data_contagem_primaria DESC
                   LIMIT ? OFFSET ?";

        $dataParams = array_merge($params, [$perPage, $offset]);
        $dataTypes  = $types . 'ii';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($dataTypes, ...$dataParams);
        $stmt->execute();

        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'items'       => $items,
            'page'        => $page,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            'total'       => $total,
        ];
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
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

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
                'message' => 'Contagem encerrada! O partnumber "' . ($row['partnumber'] ?? '') . '" foi finalizado.',
            ];
        }

        return ['success' => false, 'message' => 'Erro ao encerrar contagem.'];
    }

    // =======================================================================
    // MÉTODOS DE EXPORTAÇÃO
    // =======================================================================

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
                    CONCAT(c.deposito, ': ', COALESCE(c.quantidade_final, c.quantidade_primaria))
                    ORDER BY c.deposito SEPARATOR ' | '
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
             LEFT JOIN usuarios u1 ON c.usuario_id = u1.id
             LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
             LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
             WHERE c.inventario_id = ?
             ORDER BY c.deposito, c.partnumber"
        );
        $stmt->bind_param('i', $inventarioId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // =======================================================================
    // MÉTODOS DE COMPATIBILIDADE (não devem ser usados)
    // =======================================================================

    /**
     * Proxy público chamado pelo Controller para avançar fase ou somar.
     * Redireciona para o método correto conforme o estado do banco.
     */
    public function registrarNovaContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        $row = $this->findById($contagemId);
        if (!$row) {
            return ['success' => false, 'message' => 'Contagem não encontrada.'];
        }

        $podeNova   = (bool) $row['pode_nova_contagem'];
        $finalizado = (bool) $row['finalizado'];

        if ($finalizado) {
            return ['success' => false, 'message' => 'Esta contagem já foi finalizada.'];
        }

        if ($podeNova) {
            return $this->avancarParaProximaFase($row, $quantidade, $usuarioId);
        }

        return $this->somarNaFaseAtual($row, $quantidade);
    }

    public function registrarSegundaContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        return ['success' => false, 'message' => 'Use iniciarSegundaContagem() para liberar a segunda contagem pelo admin.'];
    }

    public function registrarTerceiraContagem(int $contagemId, float $quantidade, int $usuarioId): array
    {
        return ['success' => false, 'message' => 'Use iniciarTerceiraContagem() para liberar a terceira contagem pelo admin.'];
    }
}