<?php
declare(strict_types=1);

// ============================================
// PONTO DE ENTRADA DA APLICAÇÃO
// ============================================

define('BASE_PATH', dirname(__DIR__));
define('SRC_PATH',  BASE_PATH . '/src');

// Carregar variáveis de ambiente
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if (!empty($key)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Autoloader PSR-4 simples (sem Composer)
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = SRC_PATH . '/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Iniciar sessão com segurança
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inicializar banco de dados
use App\Database\Migrations;
Migrations::run();

// Roteador
use App\Core\Router;
$router = new Router();

// Rotas de autenticação
$router->get('login',  [App\Controllers\AuthController::class, 'showLogin']);
$router->post('login', [App\Controllers\AuthController::class, 'processLogin']);
$router->get('logout', [App\Controllers\AuthController::class, 'logout']);

// Rotas AJAX
$router->get('ajax',  [App\Controllers\AjaxController::class, 'handle']);
$router->post('ajax', [App\Controllers\AjaxController::class, 'handle']);

// Rotas de exportação
$router->get('exportar', [App\Controllers\ExportController::class, 'handle']);

// Rotas protegidas (requerem autenticação)
$router->get('dashboard',              [App\Controllers\DashboardController::class,  'index']);
$router->post('dashboard',             [App\Controllers\DashboardController::class,  'handle']);
$router->get('contagem',               [App\Controllers\ContagemController::class,   'index']);
$router->post('contagem',              [App\Controllers\ContagemController::class,   'handle']);
$router->get('cadastros',              [App\Controllers\CadastrosController::class,  'index']);
$router->post('cadastros',             [App\Controllers\CadastrosController::class,  'handle']);
$router->get('inventarios_concluidos', [App\Controllers\DashboardController::class,  'concluidos']);

$router->dispatch();
