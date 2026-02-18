<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Contagem;
use App\Models\Deposito;
use App\Models\Inventario;
use App\Models\Notificacao;
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
     * POST ?pagina=ajax&acao=verificar_status_contagem
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
            'existe'               => true,
            'id'                   => $contagem['id'],
            'numero_contagens'     => (int)$contagem['numero_contagens_realizadas'],
            'pode_nova'            => (bool)$contagem['pode_nova_contagem'],
            'finalizado'           => (bool)$contagem['finalizado'],
            'status'               => $contagem['status'],
            'quantidade_primaria'  => (float)$contagem['quantidade_primaria'],
            'quantidade_secundaria'=> $contagem['quantidade_secundaria'] !== null ? (float)$contagem['quantidade_secundaria'] : null,
            'quantidade_terceira'  => $contagem['quantidade_terceira']   !== null ? (float)$contagem['quantidade_terceira']   : null,
            'quantidade_final'     => $contagem['quantidade_final']      !== null ? (float)$contagem['quantidade_final']      : null,
        ]);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=liberar_segunda
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

        $contagemModel = new Contagem();
        $result = $contagemModel->iniciarSegundaContagem($contagemId);

        // Se sucesso, busca dados atualizados para retornar ao frontend
        if ($result['success']) {
            $updated = $contagemModel->findById($contagemId);
            if ($updated) {
                $result['data'] = [
                    'id'                 => (int)$updated['id'],
                    'numero_contagens'   => (int)($updated['numero_contagens_realizadas'] ?? 1),
                    'pode_nova_contagem' => (bool)($updated['pode_nova_contagem'] ?? false),
                    'status'             => $updated['status'] ?? 'primaria',
                    'finalizado'         => (bool)($updated['finalizado'] ?? false),
                ];
            }
        }

        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=liberar_terceira
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

        $contagemModel = new Contagem();
        $result = $contagemModel->iniciarTerceiraContagem($contagemId);

        // Se sucesso, busca dados atualizados para retornar ao frontend
        if ($result['success']) {
            $updated = $contagemModel->findById($contagemId);
            if ($updated) {
                $result['data'] = [
                    'id'                 => (int)$updated['id'],
                    'numero_contagens'   => (int)($updated['numero_contagens_realizadas'] ?? 2),
                    'pode_nova_contagem' => (bool)($updated['pode_nova_contagem'] ?? false),
                    'status'             => $updated['status'] ?? 'secundaria',
                    'finalizado'         => (bool)($updated['finalizado'] ?? false),
                ];
            }
        }

        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=finalizar_contagem
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
     * GET ?pagina=ajax&acao=notificacoes&desde=TIMESTAMP
     *
     * Endpoint de polling leve para o admin.
     * Retorna contagens registradas por operadores desde o timestamp informado.
     * O cliente JS controla o timestamp em sessionStorage — sem estado no servidor.
     */
    public function notificacoes(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // Cache 0 para o browser não cachear o polling
        header('Cache-Control: no-store');

        if (!Security::isAuthenticated() || !Security::isAdmin()) {
            echo json_encode(['total' => 0, 'items' => []]);
            exit;
        }

        $inventario = (new Inventario())->findAtivo();
        if (!$inventario) {
            echo json_encode(['total' => 0, 'items' => []]);
            exit;
        }

        // "desde" = timestamp Unix enviado pelo cliente (padrão: agora - 60s)
        $desde = (int) ($_GET['desde'] ?? (time() - 60));

        $result = (new Notificacao())->buscarDesde($inventario['id'], $desde);
        echo json_encode($result);
        exit;
    }
}