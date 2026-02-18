<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Contagem;
use App\Models\Deposito;
use App\Models\Inventario;
use App\Models\Partnumber;

class AjaxController
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode([]);
            exit;
        }

        $tipo  = $_GET['tipo']  ?? '';
        $termo = trim($_GET['termo'] ?? '');

        if (empty($termo) || mb_strlen($termo) < 2) {
            echo json_encode([]);
            exit;
        }

        $result = match ($tipo) {
            'partnumber' => (new Partnumber())->suggest($termo),
            'deposito'   => (new Deposito())->suggest($termo),
            default      => [],
        };

        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=verificar_finalizado
     * Verifica se um partnumber/depósito já está finalizado.
     */
    public function verificarFinalizado(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }

        $partnumber = Security::sanitize($_POST['partnumber'] ?? '');
        $deposito   = Security::sanitize($_POST['deposito']   ?? '');

        if (empty($partnumber) || empty($deposito)) {
            echo json_encode(['finalizado' => false, 'existe' => false]);
            exit;
        }

        $inventario = (new Inventario())->findAtivo();
        if (!$inventario) {
            echo json_encode(['finalizado' => false, 'existe' => false]);
            exit;
        }

        $result = (new Contagem())->verificarPartNumberFinalizado(
            $inventario['id'],
            $partnumber,
            $deposito
        );

        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=liberar_segunda
     * Admin libera segunda contagem
     */
    public function liberarSegunda(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }

        if (!Security::isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Apenas administradores podem liberar contagens.']);
            exit;
        }

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
            exit;
        }

        $contagemId = (int) ($_POST['contagem_id'] ?? 0);

        if ($contagemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de contagem inválido']);
            exit;
        }

        $result = (new Contagem())->iniciarSegundaContagem($contagemId);
        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=liberar_terceira
     * Admin libera terceira contagem
     */
    public function liberarTerceira(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }

        if (!Security::isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Apenas administradores podem liberar contagens.']);
            exit;
        }

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
            exit;
        }

        $contagemId = (int) ($_POST['contagem_id'] ?? 0);

        if ($contagemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de contagem inválido']);
            exit;
        }

        $result = (new Contagem())->iniciarTerceiraContagem($contagemId);
        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=finalizar_contagem
     * Finaliza uma contagem manualmente
     */
    public function finalizarContagem(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }

        if (!Security::isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Apenas administradores podem finalizar contagens.']);
            exit;
        }

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
            exit;
        }

        $contagemId = (int) ($_POST['contagem_id'] ?? 0);

        if ($contagemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de contagem inválido']);
            exit;
        }

        $result = (new Contagem())->finalizarContagem($contagemId);
        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=verificar_status_contagem
     * Verifica status atual de uma contagem (para atualizar UI)
     */
    public function verificarStatusContagem(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }

        $partnumber = strtoupper(trim(Security::sanitize($_POST['partnumber'] ?? '')));
        $deposito   = strtoupper(trim(Security::sanitize($_POST['deposito']   ?? '')));

        if (empty($partnumber) || empty($deposito)) {
            echo json_encode(['existe' => false]);
            exit;
        }

        $inventario = (new Inventario())->findAtivo();
        if (!$inventario) {
            echo json_encode(['existe' => false]);
            exit;
        }

        $contagem = (new Contagem())->findOpenByPartnumber(
            $inventario['id'],
            $partnumber,
            $deposito
        );

        if (!$contagem) {
            echo json_encode(['existe' => false]);
            exit;
        }

        echo json_encode([
            'existe' => true,
            'id' => $contagem['id'],
            'numero_contagens' => (int)$contagem['numero_contagens_realizadas'],
            'pode_nova' => (bool)$contagem['pode_nova_contagem'],
            'finalizado' => (bool)$contagem['finalizado'],
            'status' => $contagem['status'],
            'quantidade_primaria' => (float)$contagem['quantidade_primaria'],
            'quantidade_secundaria' => $contagem['quantidade_secundaria'] ? (float)$contagem['quantidade_secundaria'] : null,
            'quantidade_terceira' => $contagem['quantidade_terceira'] ? (float)$contagem['quantidade_terceira'] : null,
            'quantidade_final' => $contagem['quantidade_final'] ? (float)$contagem['quantidade_final'] : null,
        ]);
        exit;
    }
}