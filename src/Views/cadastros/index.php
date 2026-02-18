<?php

/**
 * @var string   $message
 * @var string   $csrfToken
 * @var string   $tipo          'depositos' | 'partnumbers'
 * @var array    $depositos
 * @var array    $partnumbers
 */
$pageTitle = 'Cadastros — Sistema de Inventário';
require SRC_PATH . '/Views/layout/header.php';

$msgClass = str_contains((string)$message, 'sucesso') || str_contains((string)$message, 'importado') ? 'sucesso' : 'erro';
?>

<div class="form-container">
    <h2 class="form-title"><i class="fas fa-database"></i> Cadastros</h2>

    <?php if (!empty($message)): ?>
        <div class="mensagem <?= $msgClass ?>" data-auto-hide><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?pagina=cadastros&tipo=depositos"
            class="tab <?= $tipo === 'depositos' ? 'active' : '' ?>">
            <i class="fas fa-warehouse"></i> Depósitos (<?= count($depositos) ?>)
        </a>
        <a href="?pagina=cadastros&tipo=partnumbers"
            class="tab <?= $tipo === 'partnumbers' ? 'active' : '' ?>">
            <i class="fas fa-barcode"></i> Part Numbers (<?= count($partnumbers) ?>)
        </a>
    </div>

    <?php if ($tipo === 'depositos'): ?>
    <!-- =============== DEPÓSITOS =============== -->
    <form method="POST" action="?pagina=cadastros&tipo=depositos" id="formDeposito" style="margin-bottom:25px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="acao" value="cadastrar_deposito">
        <h3 class="form-title" style="font-size:16px;"><i class="fas fa-plus"></i> Cadastrar Depósito</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:14px;align-items:end;" class="form-grid-auto">
            <div class="form-group" style="margin-bottom:0;">
                <label>Nome do Depósito:</label>
                <input type="text" name="deposito" required placeholder="Ex: ALMOX-01" id="inputDeposito">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Localização <small style="font-weight:400;color:var(--gray)">(opcional)</small>:</label>
                <input type="text" name="localizacao" placeholder="Ex: Prédio A, Galpão 2">
            </div>
            <button type="submit" class="btn btn-primary" id="btnSalvarDeposito">
                <span class="btn-text"><i class="fas fa-save"></i> Salvar</span>
            </button>
        </div>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Depósito</th>
                    <th>Localização</th>
                    <th>Registros</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($depositos)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center;padding:25px;color:var(--gray);">
                            Nenhum depósito cadastrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($depositos as $dep): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dep['deposito']) ?></strong></td>
                            <td><?= htmlspecialchars($dep['localizacao'] ?? '—') ?></td>
                            <td><?= $dep['total_registros']-1?></td>
                            <td>
                                <form method="POST" action="?pagina=cadastros&tipo=depositos"
                                    onsubmit="return confirmar('Excluir o depósito \'<?= htmlspecialchars(addslashes($dep['deposito'])) ?>\'?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="acao" value="excluir_deposito">
                                    <input type="hidden" name="deposito" value="<?= htmlspecialchars($dep['deposito']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-loading-text="" data-btn-anim="spinner">
                                        <span class="btn-text"><i class="fas fa-trash"></i></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <!-- =============== PART NUMBERS =============== -->
    <form method="POST" action="?pagina=cadastros&tipo=partnumbers" data-validate style="margin-bottom:25px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="acao" value="cadastrar_partnumber">
        <h3 class="form-title" style="font-size:16px;"><i class="fas fa-plus"></i> Cadastrar Part Number</h3>
        <div style="display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:14px;align-items:end;" class="form-grid-auto">
            <div class="form-group" style="margin-bottom:0;">
                <label>Part Number:</label>
                <input type="text" name="partnumber" required placeholder="Ex: ABC-123">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Descrição <small style="font-weight:400;color:var(--gray)">(opcional)</small>:</label>
                <input type="text" name="descricao" placeholder="Descrição do item">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Unidade:</label>
                <select name="unidade" required>
                    <?php foreach (['UN', 'CX', 'KG', 'PC', 'MT', 'LT', 'PT'] as $u): ?>
                        <option value="<?= $u ?>"><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" data-loading-text="Salvando..." data-success-text="Salvo!" data-btn-anim="spinner">
                <span class="btn-text"><i class="fas fa-save"></i> Salvar</span>
            </button>
        </div>
    </form>

    <!-- Importar CSV -->
    <details style="margin-bottom:25px;">
        <summary style="cursor:pointer;font-weight:600;color:var(--secondary);padding:10px;background:#f0f8ff;border-radius:var(--border-r);">
            <i class="fas fa-file-import"></i> Importar via CSV
        </summary>
        <div style="padding:18px;background:#f8f9fa;border-radius:0 0 var(--border-r) var(--border-r);border:1px solid #e0e0e0;border-top:none;">
            <p style="font-size:13px;color:var(--gray);margin-bottom:12px;">
                Formato: <code>partnumber;descricao;unidade</code> — uma entrada por linha, com cabeçalho.
            </p>
            <form method="POST" action="?pagina=cadastros&tipo=partnumbers" enctype="multipart/form-data"
                style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="acao" value="importar_partnumbers">
                <div>
                    <label style="font-size:13px;margin-bottom:6px;display:block;">Arquivo CSV:</label>
                    <input type="file" name="arquivo_csv" accept=".csv,.txt" required>
                </div>
                <button type="submit" class="btn btn-secondary" data-loading-text="Importando..." data-success-text="Importado!" data-btn-anim="progress">
                    <span class="btn-text"><i class="fas fa-upload"></i> Importar</span>
                </button>
            </form>
        </div>
    </details>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Part Number</th>
                    <th>Descrição</th>
                    <th>Unidade</th>
                    <th>Registros</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($partnumbers)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:25px;color:var(--gray);">
                            Nenhum part number cadastrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($partnumbers as $pn): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pn['partnumber']) ?></strong></td>
                            <td><?= htmlspecialchars($pn['descricao'] ?? '—') ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($pn['unidade_medida'] ?? 'UN') ?></span></td>
                            <td><?= $pn['total_registros'] ?></td>
                            <td>
                                <form method="POST" action="?pagina=cadastros&tipo=partnumbers"
                                    onsubmit="return confirmar('Excluir o part number \'<?= htmlspecialchars(addslashes($pn['partnumber'])) ?>\'?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="acao" value="excluir_partnumber">
                                    <input type="hidden" name="partnumber" value="<?= htmlspecialchars($pn['partnumber']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-loading-text="" data-btn-anim="spinner">
                                        <span class="btn-text"><i class="fas fa-trash"></i></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<script>
// ============================================================
// LOADING STATES NOS FORMS DE CADASTRO
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    // Adicionar IDs nos forms e botões dinamicamente se não existirem
    const formDepositoQuery = document.querySelector('form[action*="tipo=depositos"]');
    if (formDepositoQuery && !formDepositoQuery.id) {
        formDepositoQuery.id = 'formDeposito';
        const btn = formDepositoQuery.querySelector('button[type="submit"]');
        if (btn && !btn.id) btn.id = 'btnSalvarDeposito';
    }

    const formPartnumberQuery = document.querySelector('form[action*="tipo=partnumbers"]:not([enctype])');
    if (formPartnumberQuery && !formPartnumberQuery.id) {
        formPartnumberQuery.id = 'formPartnumber';
        const btn = formPartnumberQuery.querySelector('button[type="submit"]');
        if (btn && !btn.id) btn.id = 'btnSalvarPartnumber';
    }

    // Form de Depósito
    const formDeposito = document.getElementById('formDeposito');
    if (formDeposito) {
        formDeposito.addEventListener('submit', function(e) {
            if (!validateForm(formDeposito)) {
                e.preventDefault();
                return;
            }
            const btn = document.getElementById('btnSalvarDeposito');
            if (btn) btnLoading(btn, true);
        });
    }

    // Form de Part Number
    const formPartnumber = document.getElementById('formPartnumber');
    if (formPartnumber) {
        formPartnumber.addEventListener('submit', function(e) {
            if (!validateForm(formPartnumber)) {
                e.preventDefault();
                return;
            }
            const btn = document.getElementById('btnSalvarPartnumber');
            if (btn) btnLoading(btn, true);
        });
    }
});
</script>

<?php require SRC_PATH . '/Views/layout/footer.php'; ?>