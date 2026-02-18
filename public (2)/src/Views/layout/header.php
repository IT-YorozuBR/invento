<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="icon" type="image/png" href="/public/assets/Inserir um subtítulo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Sistema de Inventário' ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>

<?php
use App\Core\Security;
$currentPage = $_GET['pagina'] ?? 'login';
?>

<?php if ($currentPage !== 'login' && Security::isAuthenticated()): ?>
<nav class="navbar">
    <div class="navbar-brand">
        <i class="fas fa-boxes"></i>
        <span>Invento</span>
    </div>
    <div class="navbar-menu">
        <?php if (Security::isAdmin()): ?>
            <a href="?pagina=dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="?pagina=contagem" class="nav-link <?= $currentPage === 'contagem' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i> Contagem
            </a>
            <a href="?pagina=cadastros&tipo=depositos" class="nav-link <?= $currentPage === 'cadastros' ? 'active' : '' ?>">
                <i class="fas fa-database"></i> Cadastros
            </a>
            <a href="?pagina=inventarios_concluidos" class="nav-link <?= $currentPage === 'inventarios_concluidos' ? 'active' : '' ?>">
                <i class="fas fa-history"></i> Concluídos
            </a>
        <?php else: ?>
            <a href="?pagina=contagem" class="nav-link <?= $currentPage === 'contagem' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i> Contagem
            </a>
        <?php endif; ?>

        <div class="user-info">
            <?php if (Security::isAdmin()): ?>
            <!-- Sino de notificações — visível apenas para admin -->
            <button id="btnNotificacoes" class="notif-bell" title="Notificações de atividade" onclick="abrirPainelNotificacoes()">
                <i class="fas fa-bell"></i>
                <span id="notifBadge" class="notif-badge" style="display:none;">0</span>
            </button>
            <?php endif; ?>

            <span style="display:flex;align-items:center;gap:8px;font-size:14px;">
                <i class="fas fa-user-circle"></i>
                <?= htmlspecialchars(Security::currentUserName()) ?>
                <span class="badge badge-<?= Security::isAdmin() ? 'warning' : 'info' ?>">
                    <?= Security::isAdmin() ? 'Admin' : 'Operador' ?>
                </span>
            </span>
            <a href="?pagina=logout" class="btn btn-sm btn-outline">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</nav>

<!-- Painel de notificações (oculto por padrão) -->
<?php if (Security::isAdmin()): ?>
<div id="painelNotificacoes" class="notif-painel" style="display:none;">
    <div class="notif-painel-header">
        <span><i class="fas fa-bell"></i> Atividade dos Operadores</span>
        <button onclick="fecharPainelNotificacoes()" class="notif-fechar">&times;</button>
    </div>
    <div id="notifLista" class="notif-lista">
        <p class="notif-vazio"><i class="fas fa-check-circle"></i> Nenhuma atividade recente.</p>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<main class="container">