<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Services\ExportService;

class ExportController
{
    public function handle(): void
    {
        Security::requireAdmin();

        $inventarioId = (int) ($_GET['inventario_id'] ?? 0);
        $formato      = $_GET['formato'] ?? 'xlsx';

        if ($inventarioId <= 0) {
            http_response_code(400);
            die('ID do inventário inválido.');
        }

        (new ExportService())->export($inventarioId, $formato);
    }
}
