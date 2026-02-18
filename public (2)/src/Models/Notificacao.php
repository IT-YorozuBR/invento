<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Model de Notificações para Admin
 *
 * Design intencional:
 * - Apenas INSERT ao registrar contagem (sem UPDATE/DELETE automático)
 * - Polling via JS a cada 45 s — retorna contagens desde timestamp armazenado no cliente
 * - Limpeza automática de registros com mais de 24 h (feita no fetch, não em background)
 */
class Notificacao
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Registra uma notificação quando um operador realiza uma contagem.
     * Chamado pelo ContagemController após sucesso.
     */
    public function criar(
        int    $inventarioId,
        string $usuarioNome,
        string $partnumber,
        string $deposito,
        int    $fase = 1
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO notificacoes_admin
                (inventario_id, usuario_nome, partnumber, deposito, fase)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssi', $inventarioId, $usuarioNome, $partnumber, $deposito, $fase);
        $stmt->execute();
    }

    /**
     * Retorna notificações criadas após determinado timestamp Unix.
     * A limpeza de registros antigos foi movida para cleanup.php (cron job),
     * eliminando lock contention durante o polling concorrente de admins.
     *
     * @param  int $inventarioId
     * @param  int $desde        Timestamp Unix (segundos)
     * @return array{ total: int, items: array }
     */
    public function buscarDesde(int $inventarioId, int $desde): array
    {
        $desdeTs = date('Y-m-d H:i:s', $desde);

        $stmt = $this->db->prepare(
            'SELECT usuario_nome, partnumber, deposito, fase,
                    UNIX_TIMESTAMP(criado_em) AS ts
             FROM notificacoes_admin
             WHERE inventario_id = ? AND criado_em > ?
             ORDER BY criado_em DESC
             LIMIT 20'
        );
        $stmt->bind_param('is', $inventarioId, $desdeTs);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'total' => count($items),
            'items' => $items,
        ];
    }
}