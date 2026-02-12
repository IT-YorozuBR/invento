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

        $deposito   = strtoupper(trim(Security::sanitize($_POST['deposito']   ?? '')));
        $partnumber = strtoupper(trim(Security::sanitize($_POST['partnumber'] ?? '')));
        $quantidade = (float) ($_POST['quantidade'] ?? 0);



        $existing = $this->contagem->findOpenByPartnumber($inventarioId, $partnumber, $deposito);
        if ($existing) {
            // segurança: converter campos relevantes
            $numContagens = (int) ($existing['numero_contagens_realizadas'] ?? 1);
            $podeNova     = (int) ($existing['pode_nova_contagem'] ?? 0);

            // Se a contagem ainda permite nova contagem e não alcançou o máximo (3)
            if ($podeNova === 1 && $numContagens < 3) {
                $result = $this->contagem->registrarNovaContagem(
                    (int) $existing['id'],
                    $quantidade,
                    Security::currentUserId()
                );
                $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
                return;
            }
            // se não pode nova contagem, continua o fluxo normal (cria nova primária)
        }

        if ($quantidade <= 0) {
            $_SESSION['flash_error'] = 'Quantidade deve ser maior que zero.';
            return;
        }

        // ------------------------------------------------------------
        // 3. Verificar se há uma nova contagem ativa na sessão
        // ------------------------------------------------------------
        $sessionKey = 'nova_contagem_' . md5($inventarioId . '|' . $partnumber . '|' . $deposito);

        if (isset($_SESSION[$sessionKey])) {
            // É uma SEGUNDA (ou TERCEIRA) contagem
            $contagemId = (int) $_SESSION[$sessionKey];

            $result = $this->contagem->registrarNovaContagem(
                $contagemId,
                $quantidade,
                Security::currentUserId()
            );

            unset($_SESSION[$sessionKey]); // Remove a flag (já utilizada)

            $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
            return;
        }

        // ------------------------------------------------------------
        // 4. FLUXO NORMAL: PRIMEIRA CONTAGEM (com suporte a "outro")
        // ------------------------------------------------------------

        // Suporte a novo depósito
        if ($deposito === 'OUTRO' && !empty($_POST['nova_localizacao'])) {
            $novoDeposito = strtoupper(trim(Security::sanitize($_POST['nova_localizacao'])));
            $this->deposito->save($novoDeposito, Security::sanitize($_POST['nova_localizacao'] ?? ''));
            $deposito = $novoDeposito; // substitui para uso na contagem
        }

        // Suporte a novo partnumber
        if ($partnumber === 'OUTRO' && !empty($_POST['nova_descricao'])) {
            $novoPartnumber = strtoupper(trim(Security::sanitize($_POST['nova_descricao'])));
            $this->partnumber->save(
                $novoPartnumber,
                Security::sanitize($_POST['nova_descricao'] ?? ''),
                Security::sanitize($_POST['nova_unidade'] ?? 'UN')
            );
            $partnumber = $novoPartnumber; // substitui para uso na contagem
        }

        // ------------------------------------------------------------
        // 5. Touch nos modelos (atualiza timestamp)
        // ------------------------------------------------------------
        $this->deposito->touch($deposito);
        $this->partnumber->touch($partnumber, Security::sanitize($_POST['descricao'] ?? ''));

        // ------------------------------------------------------------
        // 6. Dados extras para a contagem
        // ------------------------------------------------------------
        $extra = [
            'descricao' => Security::sanitize($_POST['descricao'] ?? ''),
            'unidade'   => Security::sanitize($_POST['unidade']   ?? 'UN'),
            'lote'      => Security::sanitize($_POST['lote']      ?? '') ?: null,
            'validade'  => $_POST['validade'] ?? null,
        ];

        // ------------------------------------------------------------
        // 7. Registrar a primeira contagem
        // ------------------------------------------------------------
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
