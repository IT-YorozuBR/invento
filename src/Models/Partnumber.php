<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Partnumber
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        $stmt = $this->db->prepare(
            'SELECT partnumber, descricao, unidade_medida, total_registros
             FROM partnumbers_registrados ORDER BY partnumber ASC'
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function suggest(string $term): array
    {
        $like = '%' . $this->db->escape($term) . '%';
        $stmt = $this->db->prepare(
            'SELECT partnumber, descricao FROM partnumbers_registrados
             WHERE partnumber LIKE ? ORDER BY total_registros DESC, data_ultimo_registro DESC LIMIT 10'
        );
        $stmt->bind_param('s', $like);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function save(string $pn, string $descricao = '', string $unidade = 'UN'): array
    {
        if (!$this->validate($pn)) {
            return ['success' => false, 'message' => 'Part number inválido (use letras, números, - e _)'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO partnumbers_registrados (partnumber, descricao, unidade_medida)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                descricao            = COALESCE(?, descricao),
                unidade_medida       = COALESCE(?, unidade_medida),
                total_registros      = total_registros + 1,
                data_ultimo_registro = NOW()'
        );
        $stmt->bind_param('sssss', $pn, $descricao, $unidade, $descricao, $unidade);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Part number salvo com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao salvar part number'];
    }

    public function delete(string $pn): array
    {
        $stmt = $this->db->prepare('DELETE FROM partnumbers_registrados WHERE partnumber = ?');
        $stmt->bind_param('s', $pn);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Part number excluído com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao excluir part number'];
    }

    public function touch(string $pn, string $descricao = ''): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO partnumbers_registrados (partnumber, descricao)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                total_registros      = total_registros + 1,
                data_ultimo_registro = NOW()'
        );
        $stmt->bind_param('ss', $pn, $descricao);
        $stmt->execute();
    }

    public function importCsv(string $csvContent): array
    {
        $lines    = explode("\n", $csvContent);
        $success  = 0;
        $errors   = 0;
        $messages = [];

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line) || $i === 0) {
                continue; // pular cabeçalho e linhas vazias
            }

            $cols = str_getcsv($line, ';');
            $pn   = trim($cols[0] ?? '');

            if (empty($pn)) {
                $errors++;
                continue;
            }

            $result = $this->save(
                $pn,
                trim($cols[1] ?? ''),
                trim($cols[2] ?? 'UN')
            );

            if ($result['success']) {
                $success++;
            } else {
                $errors++;
                $messages[] = "Linha " . ($i + 1) . ": " . $result['message'];
            }
        }

        return ['sucessos' => $success, 'erros' => $errors, 'mensagens' => $messages];
    }

    private function validate(string $pn): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-_]{3,50}$/', $pn);
    }
}
