<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Inventario;

class DashboardController
{
    private Inventario $model;

    public function __construct()
    {
        $this->model = new Inventario();
    }

    public function index(): void
    {
        Security::requireAuth();

        // Operadores sem inventário ativo veem mensagem; com inventário ativo vão para contagem
        if (!Security::isAdmin()) {
            $ativo = $this->model->findAtivo();
            if ($ativo) {
                header('Location: ?pagina=contagem');
                exit;
            }
        }

        $message    = $this->consumeFlash();
        $csrfToken  = Security::generateCsrfToken();
        $inventario = $this->model->findAtivo();
        $stats      = $inventario ? $this->model->getEstatisticas($inventario['id']) : [];

        require SRC_PATH . '/Views/dashboard/index.php';
    }

    public function handle(): void
    {
        Security::requireAdmin();

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Token de segurança inválido.';
            header('Location: ?pagina=dashboard');
            exit;
        }

        $acao = $_POST['acao_inventario'] ?? '';

        if ($acao === 'criar') {
            $dataInicio = $_POST['data_inicio'] ?? '';
            $descricao  = Security::sanitize($_POST['descricao'] ?? '');

            if (!Security::validateDate($dataInicio)) {
                $_SESSION['flash_error'] = 'Data inválida.';
            } else {
                $result = $this->model->create($dataInicio, $descricao, Security::currentUserId());
                $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
            }
        } elseif ($acao === 'fechar') {
            $id     = (int) ($_POST['inventario_id'] ?? 0);
            $result = $this->model->fechar($id);
            $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
        }

        header('Location: ?pagina=dashboard');
        exit;
    }

    public function concluidos(): void
    {
        Security::requireAdmin();

        $page    = max(1, (int) ($_GET['p'] ?? 1));
        $message = $this->consumeFlash();
        $data    = $this->model->findConcluidos($page);

        require SRC_PATH . '/Views/dashboard/concluidos.php';
    }

    private function consumeFlash(): string
    {
        $msg = $_SESSION['flash_success'] ?? $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        return $msg;
    }
}
