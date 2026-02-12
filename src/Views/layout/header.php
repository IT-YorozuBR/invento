<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Sistema de Inventário' ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- QR Code Scanner Library -->
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
        <span>Sistema de Inventário</span>
    </div>
    <div class="navbar-menu">
        <?php if (Security::isAdmin()): ?>
            <a href="?pagina=dashboard"              class="nav-link <?= $currentPage === 'dashboard'              ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="?pagina=contagem"               class="nav-link <?= $currentPage === 'contagem'               ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i> Contagem
            </a>
            <a href="?pagina=cadastros&tipo=depositos" class="nav-link <?= $currentPage === 'cadastros'            ? 'active' : '' ?>">
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
            <span class="user-info">
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
<?php endif; ?>

<main class="container">
