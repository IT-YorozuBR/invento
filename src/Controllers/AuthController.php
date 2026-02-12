<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Inventario;
use App\Models\Usuario;

class AuthController
{
    public function showLogin(): void
    {
        if (Security::isAuthenticated()) {
            header('Location: ?pagina=dashboard');
            exit;
        }

        $message  = $_GET['timeout'] ?? false ? 'Sua sessão expirou. Por favor, faça login novamente.' : '';
        $csrfToken = Security::generateCsrfToken();
        require SRC_PATH . '/Views/auth/login.php';
    }

    public function processLogin(): void
    {
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Security::validateCsrfToken($csrfToken)) {
            $this->redirectLoginWithError('Token de segurança inválido. Tente novamente.');
            return;
        }

        $nome      = Security::sanitize($_POST['nome']      ?? '');
        $matricula = Security::sanitize($_POST['matricula'] ?? '');
        $senha     = $_POST['senha'] ?? '';

        if (empty($nome) || empty($matricula)) {
            $this->redirectLoginWithError('Preencha todos os campos obrigatórios.');
            return;
        }

        $model    = new Usuario();
        $adminPwd = $_ENV['ADMIN_PASSWORD'] ?? '';

        if ($matricula === 'admin') {
            if (empty($senha) || $senha !== $adminPwd) {
                $this->redirectLoginWithError('Credenciais administrativas inválidas.');
                return;
            }
            $userId = $model->findOrCreate($nome, 'admin', 'admin');
            $tipo   = 'admin';
        } else {
            $userId = $model->findOrCreate($nome, $matricula, 'operador');
            $tipo   = 'operador';
        }

        if ($userId === null) {
            $this->redirectLoginWithError('Erro ao processar login. Tente novamente.');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id']       = $userId;
        $_SESSION['usuario_nome']     = $nome;
        $_SESSION['usuario_matricula'] = $matricula;
        $_SESSION['usuario_tipo']     = $tipo;
        $_SESSION['login_time']       = time();
        $_SESSION['last_activity']    = time();

        if ($tipo === 'admin') {
            header('Location: ?pagina=dashboard');
        } else {
            $inventario = (new Inventario())->findAtivo();
            header('Location: ?pagina=' . ($inventario ? 'contagem' : 'dashboard'));
        }
        exit;
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        header('Location: ?pagina=login');
        exit;
    }

    private function redirectLoginWithError(string $message): void
    {
        $_SESSION['flash_error'] = $message;
        header('Location: ?pagina=login');
        exit;
    }
}
