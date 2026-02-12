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
        } elseif ($acao === 'segunda_contagem') {
            $this->processSegundaContagem();
        }

        header('Location: ?pagina=contagem');
        exit;
    }

    // -----------------------------------------------------------------------
    // Private
    // -----------------------------------------------------------------------

    private function processRegistro(int $inventarioId): void
    {
        $deposito   = Security::sanitize($_POST['deposito']   ?? '');
        $partnumber = Security::sanitize($_POST['partnumber'] ?? '');

        // Suporte a "outro" (novo depósito/partnumber)
        if ($deposito === 'outro' && !empty($_POST['novo_deposito'])) {
            $deposito = Security::sanitize($_POST['novo_deposito']);
            $this->deposito->save($deposito, Security::sanitize($_POST['nova_localizacao'] ?? ''));
        }

        if ($partnumber === 'outro' && !empty($_POST['novo_partnumber'])) {
            $partnumber = Security::sanitize($_POST['novo_partnumber']);
            $this->partnumber->save(
                $partnumber,
                Security::sanitize($_POST['nova_descricao'] ?? ''),
                Security::sanitize($_POST['nova_unidade']   ?? 'UN')
            );
        }

        $quantidade = (float) ($_POST['quantidade'] ?? 0);

        if ($quantidade <= 0) {
            $_SESSION['flash_error'] = 'Quantidade deve ser maior que zero.';
            return;
        }

        // ---------------------------------------------------------------
        // Verificar se este partnumber+deposito está ENCERRADO
        // ---------------------------------------------------------------
        $statusAtual = $this->contagem->verificarPartNumberFinalizado(
            $inventarioId,
            $partnumber,
            $deposito
        );

        if ($statusAtual['finalizado']) {
            $_SESSION['flash_error'] =
                "⚠️ ERRO: O partnumber \"{$partnumber}\" no depósito \"{$deposito}\" já foi ENCERRADO! " .
                "Não é possível registrar novas contagens.";
            return;
        }

        // ---------------------------------------------------------------
        // Verificar se há uma "nova contagem" pendente na sessão para
        // este partnumber+deposito (ativado pelo botão "Nova Contagem")
        // ---------------------------------------------------------------
        $sessionKey   = 'nova_contagem_' . md5($inventarioId . '|' . $partnumber . '|' . $deposito);
        $contagemId   = (int) ($_SESSION[$sessionKey] ?? 0);

        if ($contagemId > 0) {
            // Há uma contagem aguardando 2ª ou 3ª leitura — processar silenciosamente
            $validacao = $this->contagem->podeIniciarNovaContagem($contagemId);

            if ($validacao['pode']) {
                $result = $this->contagem->registrarNovaContagem(
                    $contagemId,
                    $quantidade,
                    Security::currentUserId()
                );
                // Limpar a sessão após registrar
                unset($_SESSION[$sessionKey]);

                $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
                return;
            }

            // Se não pode mais (ex: já tem 3), limpar sessão e continuar fluxo normal
            unset($_SESSION[$sessionKey]);
        }

        // ---------------------------------------------------------------
        // Fluxo normal: registrar como contagem primária
        // ---------------------------------------------------------------
        $this->deposito->touch($deposito);
        $this->partnumber->touch($partnumber, Security::sanitize($_POST['descricao'] ?? ''));

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

    private function processSegundaContagem(): void
    {
        $contagemId = (int) ($_POST['contagem_id']         ?? 0);
        $quantidade = (float) ($_POST['quantidade_secundaria'] ?? 0);

        if ($contagemId <= 0 || $quantidade <= 0) {
            $_SESSION['flash_error'] = 'Dados inválidos para segunda contagem.';
            return;
        }

        $result = $this->contagem->registrarSegundaContagem(
            $contagemId,
            $quantidade,
            Security::currentUserId()
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
