<?php

/** @var string $csrfToken */
/** @var string $message */
$pageTitle = 'Login — Sistema de Inventário';
require SRC_PATH . '/Views/layout/header.php';

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h1><i class="fas fa-boxes"></i> Sistema de Inventário</h1>
            <p>Controle de Inventário Profissional</p>
        </div>

        <?php if (!empty($flashError)): ?>
            <div class="mensagem erro" data-auto-hide><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <?php if (!empty($_GET['timeout'])): ?>
            <div class="mensagem aviso" data-auto-hide>
                <i class="fas fa-clock"></i> Sessão expirada. Por favor, faça login novamente.
            </div>
        <?php endif; ?>

        <form method="POST" action="?pagina=login" data-validate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="nome"><i class="fas fa-user"></i> Nome Completo:</label>
                <input type="text" id="nome" name="nome" required
                    placeholder="Digite seu nome completo"
                    autocomplete="name">
            </div>

            <div class="form-group">
                <label for="matricula"><i class="fas fa-id-card"></i> Matrícula:</label>
                <input type="text" id="matricula" name="matricula" required
                    placeholder="Digite sua matrícula"
                    autocomplete="username">
            </div>

            <!-- Grupo do campo senha – inicialmente oculto -->
            <div id="passwordFieldGroup" class="form-group" style="display: none;">
                <label for="senha"><i class="fas fa-lock"></i> Senha <small style="font-weight:400;color:var(--gray);">(obrigatório para administrador)</small>:</label>
                <input type="password" id="senha" name="senha"
                    placeholder="Senha do administrador"
                    autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                <i class="fas fa-sign-in-alt"></i> Acessar Sistema
            </button>
        </form>

        <div style="margin-top:24px;padding:18px;background:#f8f9fa;border-radius:var(--border-r);text-align:left;font-size:13px;color:var(--gray);">
            <strong style="color:var(--primary);display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                <i class="fas fa-info-circle"></i> Informações de Acesso
            </strong>
            <ul style="padding-left:18px;line-height:1.8;">
                <li><strong>Operadores:</strong> apenas nome e matrícula</li>
                <li><strong>Administrador:</strong> matrícula <code>admin</code> + senha</li>
                <li><strong>Dúvidas:</strong> contate o supervisor</li>
            </ul>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';

        const nomeInput = document.getElementById('nome');
        const matriculaInput = document.getElementById('matricula');
        const passwordGroup = document.getElementById('passwordFieldGroup');
        const passwordInput = document.getElementById('senha');

        // Função que verifica os campos e mostra/oculta a senha
        function togglePasswordField() {
            const nome = nomeInput.value.trim().toLowerCase();
            const matricula = matriculaInput.value.trim().toLowerCase();

            const deveMostrar = (nome === 'administrador' && matricula === 'admin');

            if (deveMostrar) {
                // Exibe o grupo e torna o campo obrigatório
                passwordGroup.style.display = 'block';
                passwordInput.setAttribute('required', 'required');
            } else {
                // Oculta o grupo, remove a obrigatoriedade e limpa o campo (opcional)
                passwordGroup.style.display = 'none';
                passwordInput.removeAttribute('required');
                passwordInput.value = ''; // limpa qualquer senha digitada anteriormente
            }
        }

        // Adiciona os ouvintes de evento
        nomeInput.addEventListener('input', togglePasswordField);
        matriculaInput.addEventListener('input', togglePasswordField);

        // Executa ao carregar a página (caso o navegador preencha automaticamente)
        document.addEventListener('DOMContentLoaded', togglePasswordField);
    })();
</script>

<?php require SRC_PATH . '/Views/layout/footer.php'; ?>