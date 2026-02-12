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
     * POST ?pagina=ajax&acao=cancelar_nova_contagem
     * Remove da sessão a ativação de nova contagem.
     */
    public function cancelarNovaContagem(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated() || !Security::isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Permissão negada']);
            exit;
        }

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }

        $partnumber   = Security::sanitize($_POST['partnumber']   ?? '');
        $deposito     = Security::sanitize($_POST['deposito']     ?? '');
        $inventarioId = (int) ($_POST['inventario_id'] ?? 0);

        if (empty($partnumber) || empty($deposito) || $inventarioId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        $sessionKey = 'nova_contagem_' . md5($inventarioId . '|' . $partnumber . '|' . $deposito);
        unset($_SESSION[$sessionKey]);

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=verificar_sessao_contagem
     * Verifica se há nova contagem ativa na sessão para o PN+depósito.
     */
    public function verificarSessaoContagem(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['ativa' => false]);
            exit;
        }

        $partnumber   = Security::sanitize($_POST['partnumber']   ?? '');
        $deposito     = Security::sanitize($_POST['deposito']     ?? '');
        $inventarioId = (int) ($_POST['inventario_id'] ?? 0);

        if (empty($partnumber) || empty($deposito) || $inventarioId <= 0) {
            echo json_encode(['ativa' => false]);
            exit;
        }

        $sessionKey = 'nova_contagem_' . md5($inventarioId . '|' . $partnumber . '|' . $deposito);
        $ativa      = isset($_SESSION[$sessionKey]) && (int)$_SESSION[$sessionKey] > 0;

        echo json_encode(['ativa' => $ativa]);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=ativar_nova_contagem
     * Ativa na sessão que o próximo registro deste partnumber+deposito
     * deve ser tratado como 2ª ou 3ª contagem (sem exibir modal ao operador).
     */
    public function ativarNovaContagem(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }

        if (!Security::isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Permissão negada']);
            exit;
        }

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }

        $contagemId   = (int) ($_POST['contagem_id']  ?? 0);
        $partnumber   = Security::sanitize($_POST['partnumber']  ?? '');
        $deposito     = Security::sanitize($_POST['deposito']    ?? '');
        $inventarioId = (int) ($_POST['inventario_id'] ?? 0);

        // ===== NORMALIZAÇÃO OBRIGATÓRIA =====
        $partnumber = strtoupper(trim($partnumber));
        $deposito   = strtoupper(trim($deposito));
        // ====================================

        if ($contagemId <= 0 || $partnumber === '' || $deposito === '' || $inventarioId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        $model     = new Contagem();
        $validacao = $model->podeIniciarNovaContagem($contagemId);

        if (!$validacao['pode']) {
            echo json_encode(['success' => false, 'message' => $validacao['mensagem']]);
            exit;
        }

        // Geração da chave APÓS normalização
        $sessionKey = 'nova_contagem_' . md5($inventarioId . '|' . $partnumber . '|' . $deposito);
        $_SESSION[$sessionKey] = $contagemId;

        echo json_encode([
            'success'  => true,
            'proxima'  => $validacao['numero_proxima_contagem'],
            'message'  => 'Pronto! A próxima leitura do PN será registrada como ' .
                $validacao['numero_proxima_contagem'] . 'ª contagem.',
        ]);
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
     * POST ?pagina=ajax&acao=nova_contagem
     * Registra 2ª ou 3ª contagem.
     */
    public function novaContagem(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Security::isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
            exit;
        }

        $contagemId = (int) ($_POST['contagem_id'] ?? 0);
        $quantidade = (float) ($_POST['quantidade'] ?? 0);

        if ($contagemId <= 0 || $quantidade <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos. Quantidade deve ser maior que zero.']);
            exit;
        }

        $model     = new Contagem();
        $validacao = $model->podeIniciarNovaContagem($contagemId);

        if (!$validacao['pode']) {
            echo json_encode(['success' => false, 'message' => $validacao['mensagem']]);
            exit;
        }

        $result = $model->registrarNovaContagem($contagemId, $quantidade, Security::currentUserId());
        echo json_encode($result);
        exit;
    }

    /**
     * POST ?pagina=ajax&acao=finalizar_contagem
     * Finaliza uma contagem.
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
}
