<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Contagem;
use App\Models\Deposito;
use App\Models\Inventario;
use App\Models\Notificacao;
use App\Models\Partnumber;

class ContagemController
{
    private Contagem    $contagem;
    private Inventario  $inventario;
    private Deposito    $deposito;
    private Partnumber  $partnumber;
    private Notificacao $notificacao;

    public function __construct()
    {
        $this->contagem    = new Contagem();
        $this->inventario  = new Inventario();
        $this->deposito    = new Deposito();
        $this->partnumber  = new Partnumber();
        $this->notificacao = new Notificacao();
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
            'status'     => $_GET['status']     ?? '',
            'partnumber' => $_GET['partnumber'] ?? '',
            'deposito'   => $_GET['deposito']    ?? '',
        ];

        $depositos   = $this->deposito->all();
        $partnumbers = $this->partnumber->all();
        $pagination  = $this->contagem->findPaginated($inventarioAtivo['id'], $page, $filters);
        $stats       = $this->inventario->getEstatisticas($inventarioAtivo['id']);

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
    // Private Methods
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

        // ---------------------------------------------------------------
        // Suporte a "OUTRO" (novo depósito/partnumber)
        // ---------------------------------------------------------------
        if ($deposito === 'OUTRO' && !empty($_POST['nova_localizacao'])) {
            $novoDeposito = strtoupper(trim(Security::sanitize($_POST['nova_localizacao'])));
            $this->deposito->save($novoDeposito, Security::sanitize($_POST['nova_localizacao'] ?? ''));
            $deposito = $novoDeposito;
        }

        if ($partnumber === 'OUTRO' && !empty($_POST['nova_descricao'])) {
            $novoPartnumber = strtoupper(trim(Security::sanitize($_POST['nova_descricao'])));
            $this->partnumber->save(
                $novoPartnumber,
                Security::sanitize($_POST['nova_descricao'] ?? ''),
                Security::sanitize($_POST['nova_unidade'] ?? 'UN')
            );
            $partnumber = $novoPartnumber;
        }

        // ---------------------------------------------------------------
        // Touch (atualizar timestamp de uso)
        // ---------------------------------------------------------------
        $this->deposito->touch($deposito);
        $this->partnumber->touch($partnumber, Security::sanitize($_POST['descricao'] ?? ''));

        // ---------------------------------------------------------------
        // Dados extras para a contagem
        // ---------------------------------------------------------------
        $extra = [
            'descricao' => Security::sanitize($_POST['descricao'] ?? ''),
            'unidade'   => Security::sanitize($_POST['unidade']   ?? 'UN'),
            'lote'      => Security::sanitize($_POST['lote']      ?? '') ?: null,
            'validade'  => $_POST['validade'] ?? null,
        ];

        // ---------------------------------------------------------------
        // NOVA LÓGICA: Sempre usa registrarPrimaria()
        // O sistema decide automaticamente:
        // - Se não existe: cria primeira contagem
        // - Se existe e pode_nova = FALSE: SOMA na fase atual
        // - Se existe e pode_nova = TRUE: Avança para próxima fase
        // ---------------------------------------------------------------
        $result = $this->contagem->registrarPrimaria(
            $inventarioId,
            Security::currentUserId(),
            $deposito,
            $partnumber,
            $quantidade,
            $extra
        );

        // ---------------------------------------------------------------
        // Notificar admin (apenas se operário não for admin)
        // ---------------------------------------------------------------
        if ($result['success'] && !Security::isAdmin()) {
            try {
                // Buscar contagem atual para saber qual fase está
                $contagemAtual = $this->contagem->findOpenByPartnumber($inventarioId, $partnumber, $deposito);
                $fase = $contagemAtual ? (int)$contagemAtual['numero_contagens_realizadas'] : 1;
                
                $this->notificacao->criar(
                    $inventarioId,
                    Security::currentUserName(),
                    $partnumber,
                    $deposito,
                    $fase
                );
            } catch (\Throwable $e) {
                // Notificação é não-crítica: nunca bloquear o fluxo principal
                error_log('Notificacao::criar falhou: ' . $e->getMessage());
            }
        }

        $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
    }

    private function consumeFlash(): string
    {
        $msg = $_SESSION['flash_success'] ?? $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        return $msg;
    }
}