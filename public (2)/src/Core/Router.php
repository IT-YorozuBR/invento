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

        if ($page === '' || $page === '/') {
            $page = 'login';
        }

        // ---------------------------------------------------------------
        // Roteamento especial para ações AJAX
        // ---------------------------------------------------------------
        if ($page === 'ajax') {
            $acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

            // Mapa de ações POST (requerem CSRF)
            if ($method === 'POST') {
                $postMap = [
                    'verificar_finalizado'      => 'verificarFinalizado',
                    'verificar_status_contagem' => 'verificarStatusContagem',
                    'liberar_segunda'           => 'liberarSegunda',
                    'liberar_terceira'          => 'liberarTerceira',
                    'finalizar_contagem'        => 'finalizarContagem',
                ];
                if (isset($postMap[$acao])) {
                    $controller = new \App\Controllers\AjaxController();
                    $controller->{$postMap[$acao]}();
                    return;
                }
            }

            // Mapa de ações GET (sem CSRF — somente leitura)
            if ($method === 'GET') {
                $getMap = [
                    'notificacoes' => 'notificacoes',
                ];
                if (isset($getMap[$acao])) {
                    $controller = new \App\Controllers\AjaxController();
                    $controller->{$getMap[$acao]}();
                    return;
                }
            }

            // Fallback: autocomplete (handle padrão)
            $controller = new \App\Controllers\AjaxController();
            $controller->handle();
            return;
        }

        $handler = $this->routes[$method][$page]
            ?? $this->routes['GET'][$page]
            ?? null;

        if ($handler === null) {
            $this->notFound();
            return;
        }

        [$controllerClass, $actionMethod] = $handler;

        if (!class_exists($controllerClass)) {
            $this->notFound();
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $actionMethod)) {
            $this->notFound();
            return;
        }

        $controller->$actionMethod();
    }

    private function notFound(): void
    {
        http_response_code(404);
        $view = SRC_PATH . '/Views/errors/404.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '<h1>404 - Página não encontrada</h1>';
            echo '<a href="?pagina=dashboard">Voltar ao Dashboard</a>';
        }
    }
}