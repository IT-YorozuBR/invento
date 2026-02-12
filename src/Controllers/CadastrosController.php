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
        Security::requireAdmin();

        $tipo       = $_GET['tipo'] ?? 'depositos';
        $message    = $this->consumeFlash();
        $csrfToken  = Security::generateCsrfToken();
        $depositos  = $this->deposito->all();
        $partnumbers = $this->partnumber->all();

        require SRC_PATH . '/Views/cadastros/index.php';
    }

    public function handle(): void
    {
        Security::requireAdmin();

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Token de segurança inválido.';
            header('Location: ?pagina=cadastros');
            exit;
        }

        $acao = $_POST['acao'] ?? '';
        $tipo = $_GET['tipo']  ?? 'depositos';

        switch ($acao) {
            case 'cadastrar_deposito':
                $result = $this->deposito->save(
                    Security::sanitize($_POST['deposito']    ?? ''),
                    Security::sanitize($_POST['localizacao'] ?? '')
                );
                break;

            case 'excluir_deposito':
                $result = $this->deposito->delete(Security::sanitize($_POST['deposito'] ?? ''));
                break;

            case 'cadastrar_partnumber':
                $result = $this->partnumber->save(
                    Security::sanitize($_POST['partnumber']    ?? ''),
                    Security::sanitize($_POST['descricao']     ?? ''),
                    Security::sanitize($_POST['unidade_medida'] ?? 'UN')
                );
                break;

            case 'excluir_partnumber':
                $result = $this->partnumber->delete(Security::sanitize($_POST['partnumber'] ?? ''));
                break;

            case 'importar_partnumbers':
                $result = $this->importPartnumbers();
                break;

            default:
                $result = ['success' => false, 'message' => 'Ação desconhecida'];
        }

        $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
        header("Location: ?pagina=cadastros&tipo={$tipo}");
        exit;
    }

    private function importPartnumbers(): array
    {
        if (!isset($_FILES['arquivo_csv']) || $_FILES['arquivo_csv']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Erro no upload do arquivo.'];
        }

        $content = file_get_contents($_FILES['arquivo_csv']['tmp_name']);
        if ($content === false) {
            return ['success' => false, 'message' => 'Não foi possível ler o arquivo.'];
        }

        $result = $this->partnumber->importCsv($content);

        if ($result['sucessos'] > 0) {
            $msg = "{$result['sucessos']} part number(s) importado(s) com sucesso.";
            if ($result['erros'] > 0) {
                $msg .= " {$result['erros']} erro(s) ignorado(s).";
            }
            return ['success' => true, 'message' => $msg];
        }

        return ['success' => false, 'message' => 'Nenhum part number importado. Verifique o formato (CSV separado por ;).'];
    }

    private function consumeFlash(): string
    {
        $msg = $_SESSION['flash_success'] ?? $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        return $msg;
    }
}
