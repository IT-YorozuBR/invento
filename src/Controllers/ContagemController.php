<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Contagem;
use App\Models\Deposito;
use App\Models\Inventario;
use App\Models\Partnumber;

class ContagemController
{
    private Contagem   $contagem;
    private Inventario $inventario;
    private Deposito   $deposito;
    private Partnumber $partnumber;

    public function __construct()
    {
        $this->contagem   = new Contagem();
        $this->inventario = new Inventario();
        $this->deposito   = new Deposito();
        $this->partnumber = new Partnumber();
    }

    public function index(): void
    {
        Security::requireAuth();

        $inventarioAtivo = $this->inventario->findAtivo();

        if (!$inventarioAtivo) {
            $_SESSION['flash_error'] = 'Não há inventário ativo no momento.';
            header('Location: ?pagina=dashboard');
            exit;
        }

        $message   = $this->consumeFlash();
        $csrfToken = Security::generateCsrfToken();
        $page      = max(1, (int) ($_GET['p'] ?? 1));
        $filters   = [
            'status'      => $_GET['status']      ?? '',
            'partnumber'  => $_GET['partnumber']  ?? '',
            'deposito'    => $_GET['deposito']     ?? '',
        ];

        $depositos  = $this->deposito->all();
        $partnumbers = $this->partnumber->all();
        $pagination = $this->contagem->findPaginated($inventarioAtivo['id'], $page, $filters);
        $stats      = $this->inventario->getEstatisticas($inventarioAtivo['id']);

        require SRC_PATH . '/Views/contagem/index.php';
    }

    public function handle(): void
    {
        Security::requireAuth();

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Token de segurança inválido.';
            header('Location: ?pagina=contagem');
            exit;
        }

        $inventarioAtivo = $this->inventario->findAtivo();
        if (!$inventarioAtivo) {
            $_SESSION['flash_error'] = 'Não há inventário ativo.';
            header('Location: ?pagina=dashboard');
            exit;
        }

        $acao = $_POST['acao_contagem'] ?? '';

        if ($acao === 'registrar') {
            $this->processRegistro($inventarioAtivo['id']);
        }

        header('Location: ?pagina=contagem');
        exit;
    }

    // -----------------------------------------------------------------------
    // Private
    // -----------------------------------------------------------------------

    private function processRegistro(int $inventarioId): void
    {
        $deposito   = strtoupper(trim(Security::sanitize($_POST['deposito']   ?? '')));
        $partnumber = strtoupper(trim(Security::sanitize($_POST['partnumber'] ?? '')));
        $quantidade = (float) ($_POST['quantidade'] ?? 0);

        if (empty($deposito) || empty($partnumber)) {
            $_SESSION['flash_error'] = 'Depósito e Part Number são obrigatórios.';
            return;
        }

        if ($quantidade <= 0) {
            $_SESSION['flash_error'] = 'Quantidade deve ser maior que zero.';
            return;
        }

        // Verificar se já existe contagem aberta para este PN + depósito
        $existing = $this->contagem->findOpenByPartnumber($inventarioId, $partnumber, $deposito);

        if ($existing) {
            // Já existe uma contagem aberta — somar ou avançar fase via Model
            $result = $this->contagem->registrarNovaContagem(
                (int) $existing['id'],
                $quantidade,
                Security::currentUserId()
            );
            $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
            return;
        }

        // ------------------------------------------------------------
        // Não existe contagem aberta: criar a PRIMEIRA CONTAGEM
        // ------------------------------------------------------------

        // Suporte a novo depósito
        if ($deposito === 'OUTRO' && !empty($_POST['nova_localizacao'])) {
            $novoDeposito = strtoupper(trim(Security::sanitize($_POST['nova_localizacao'])));
            $this->deposito->save($novoDeposito, Security::sanitize($_POST['nova_localizacao'] ?? ''));
            $deposito = $novoDeposito;
        }

        // Suporte a novo partnumber
        if ($partnumber === 'OUTRO' && !empty($_POST['nova_descricao'])) {
            $novoPartnumber = strtoupper(trim(Security::sanitize($_POST['nova_descricao'])));
            $this->partnumber->save(
                $novoPartnumber,
                Security::sanitize($_POST['nova_descricao'] ?? ''),
                Security::sanitize($_POST['nova_unidade'] ?? 'UN')
            );
            $partnumber = $novoPartnumber;
        }

        // Touch nos modelos
        $this->deposito->touch($deposito);
        $this->partnumber->touch($partnumber, Security::sanitize($_POST['descricao'] ?? ''));

        // Dados extras para a contagem
        $extra = [
            'descricao' => Security::sanitize($_POST['descricao'] ?? ''),
            'unidade'   => Security::sanitize($_POST['unidade']   ?? 'UN'),
            'lote'      => Security::sanitize($_POST['lote']      ?? '') ?: null,
            'validade'  => $_POST['validade'] ?? null,
        ];

        $result = $this->contagem->registrarPrimaria(
            $inventarioId,
            Security::currentUserId(),
            $deposito,
            $partnumber,
            $quantidade,
            $extra
        );

        $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
    }

    private function consumeFlash(): string
    {
        $msg = $_SESSION['flash_success'] ?? $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        return $msg;
    }
}