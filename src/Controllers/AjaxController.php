<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\Deposito;
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
}
