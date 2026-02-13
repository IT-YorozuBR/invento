<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var array<string, array<string, array{0:string, 1:string}>> */
    private array $routes = [];

    public function get(string $page, array $handler): void
    {
        $this->routes['GET'][$page] = $handler;
    }

    public function post(string $page, array $handler): void
    {
        $this->routes['POST'][$page] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $page   = $_GET['pagina'] ?? 'login';

        // Redirecionar página raiz para login
        if ($page === '' || $page === '/') {
            $page = 'login';
        }

        // Roteamento especial para ações AJAX (POST ?pagina=ajax&acao=xxx)
        if ($page === 'ajax' && $method === 'POST') {
            $acao = $_GET['acao'] ?? $_POST['acao'] ?? '';
            $ajaxMethodMap = [
                'verificar_finalizado'      => 'verificarFinalizado',
                'verificar_status_contagem' => 'verificarStatusContagem',
                'liberar_segunda'           => 'liberarSegunda',
                'liberar_terceira'          => 'liberarTerceira',
                'finalizar_contagem'        => 'finalizarContagem',
            ];
            if (isset($ajaxMethodMap[$acao])) {
                $controllerClass = \App\Controllers\AjaxController::class;
                $actionMethod    = $ajaxMethodMap[$acao];
                $controller      = new $controllerClass();
                $controller->$actionMethod();
                return;
            }
        }

        $handler = $this->routes[$method][$page]
            ?? $this->routes['GET'][$page]
            ?? null;

        if ($handler === null) {
            $this->notFound();
            return;
        }

        [$controllerClass, $method] = $handler;

        if (!class_exists($controllerClass)) {
            $this->notFound();
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            $this->notFound();
            return;
        }

        $controller->$method();
    }

    private function notFound(): void
    {
        http_response_code(404);
        // Exibir view de 404 se existir, senão mensagem simples
        $view = SRC_PATH . '/Views/errors/404.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '<h1>404 - Página não encontrada</h1>';
            echo '<a href="?pagina=dashboard">Voltar ao Dashboard</a>';
        }
    }
}