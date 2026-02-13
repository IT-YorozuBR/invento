<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Deposito;
use App\Models\Partnumber;

class CadastrosController
{
    private Deposito   $deposito;
    private Partnumber $partnumber;

    public function __construct()
    {
        $this->deposito   = new Deposito();
        $this->partnumber = new Partnumber();
    }

    public function index(): void
    {
        Security::requireAuth();
        Security::requireAdmin(); // Apenas admin pode acessar cadastros

        $message   = $this->consumeFlash();
        $csrfToken = Security::generateCsrfToken();

        // Buscar todos os depósitos e partnumbers cadastrados
        $depositos   = $this->deposito->all();
        $partnumbers = $this->partnumber->all();

        require SRC_PATH . '/Views/cadastros/index.php';
    }

    public function handle(): void
    {
        Security::requireAuth();
        Security::requireAdmin();

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Token de segurança inválido.';
            header('Location: ?pagina=cadastros');
            exit;
        }

        $acao = $_POST['acao'] ?? '';

        if ($acao === 'cadastrar_deposito') {
            $this->cadastrarDeposito();
        } elseif ($acao === 'cadastrar_partnumber') {
            $this->cadastrarPartnumber();
        } elseif ($acao === 'excluir_deposito') {
            $this->excluirDeposito();
        } elseif ($acao === 'excluir_partnumber') {
            $this->excluirPartnumber();
        }

        header('Location: ?pagina=cadastros');
        exit;
    }

    // -----------------------------------------------------------------------
    // Private Methods
    // -----------------------------------------------------------------------

    private function cadastrarDeposito(): void
    {
        $deposito   = strtoupper(trim(Security::sanitize($_POST['deposito'] ?? '')));
        $localizacao = Security::sanitize($_POST['localizacao'] ?? '');

        if (empty($deposito)) {
            $_SESSION['flash_error'] = 'Nome do depósito é obrigatório.';
            return;
        }

        $result = $this->deposito->save($deposito, $localizacao);

        if ($result) {
            $_SESSION['flash_success'] = "Depósito '{$deposito}' cadastrado com sucesso!";
        } else {
            $_SESSION['flash_error'] = 'Erro ao cadastrar depósito. Pode já existir.';
        }
    }

    private function cadastrarPartnumber(): void
    {
        $partnumber = strtoupper(trim(Security::sanitize($_POST['partnumber'] ?? '')));
        $descricao  = Security::sanitize($_POST['descricao'] ?? '');
        $unidade    = Security::sanitize($_POST['unidade'] ?? 'UN');

        if (empty($partnumber)) {
            $_SESSION['flash_error'] = 'Part Number é obrigatório.';
            return;
        }

        $result = $this->partnumber->save($partnumber, $descricao, $unidade);

        if ($result) {
            $_SESSION['flash_success'] = "Part Number '{$partnumber}' cadastrado com sucesso!";
        } else {
            $_SESSION['flash_error'] = 'Erro ao cadastrar Part Number. Pode já existir.';
        }
    }

    private function excluirDeposito(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            return;
        }

        // Aqui você pode adicionar lógica de exclusão se tiver o método no model
        $_SESSION['flash_error'] = 'Funcionalidade de exclusão ainda não implementada.';
    }

    private function excluirPartnumber(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            return;
        }

        // Aqui você pode adicionar lógica de exclusão se tiver o método no model
        $_SESSION['flash_error'] = 'Funcionalidade de exclusão ainda não implementada.';
    }

    private function consumeFlash(): string
    {
        $msg = $_SESSION['flash_success'] ?? $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        return $msg;
    }
}