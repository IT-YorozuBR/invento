<?php

/**
 * @var string   $message
 * @var string   $csrfToken
 * @var array    $inventarioAtivo
 * @var array    $depositos
 * @var array    $partnumbers
 * @var array    $pagination      { items, page, total_pages, total }
 * @var array    $stats
 */

use App\Core\Security;

$pageTitle = 'Contagem — Sistema de Inventário';
require SRC_PATH . '/Views/layout/header.php';

$isAdmin = Security::isAdmin();
$msgClass = str_contains((string) $message, 'sucesso') || str_contains((string) $message, 'registrada') || str_contains((string) $message, 'atualizada')
    ? 'sucesso' : 'erro';
?>

<!-- Flash message -->
<?php if (!empty($message)): ?>
    <div class="mensagem <?= $msgClass ?>" data-auto-hide><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Formulário de Contagem -->
<div class="form-container">
    <h2 class="form-title">
        <i class="fas fa-clipboard-check"></i> Registrar Contagem
        <span style="font-size:13px;color:var(--gray);font-weight:400;margin-left:auto;display:flex;align-items:center;gap:10px;">
            Inventário: <strong><?= htmlspecialchars($inventarioAtivo['codigo']) ?></strong>
            <button type="button" onclick="iniciarScannerQR()" class="btn btn-sm btn-secondary" 
                    style="margin-left:10px;" title="Ler QR Code com a câmera">
                <i class="fas fa-qrcode"></i> Scan QR
            </button>
        </span>
    </h2>

    <form id="formContagem" method="POST" action="?pagina=contagem" data-validate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="acao_contagem" value="registrar">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="form-grid-2">

            <!-- Depósito -->
            <div class="form-group">
                <label for="depositoInput"><i class="fas fa-warehouse"></i> Depósito:</label>
                <div class="autocomplete-container">
                    <input type="text"
                        id="depositoInput"
                        name="deposito"
                        required
                        placeholder="Digite ou selecione o depósito"
                        autocomplete="off">

                    <div id="depositoDropdown"
                        class="autocomplete-dropdown"
                        style="display:none;"></div>
                </div>

                <div id="novoDepositoDiv" style="display:none;margin-top:10px;">
                    <input type="text" name="nova_localizacao"
                        placeholder="Localização (opcional)">
                </div>
            </div>


            <!-- Part Number -->
            <div class="form-group">
                <label for="partnumberInput"><i class="fas fa-barcode"></i> Part Number:</label>
                <div class="autocomplete-container">
                    <input type="text" id="partnumberInput" name="partnumber" required
                        placeholder="Digite ou selecione o part number"
                        autocomplete="off">
                    <div id="pnDropdown" class="autocomplete-dropdown" style="display:none;"></div>
                </div>
                <div id="novoPnDiv" style="display:none;margin-top:10px;">
                    <input type="text" name="nova_descricao" placeholder="Descrição (opcional)">
                    <input type="text" name="nova_unidade" placeholder="Unidade (ex: UN, CX)" style="margin-top:8px;">
                </div>
            </div>

            <!-- Quantidade -->
            <div class="form-group">
                <label for="quantidade"><i class="fas fa-sort-numeric-up"></i> Quantidade:</label>
                <input type="number" id="quantidade" name="quantidade" required
                    min="0.0001" step="0.0001" placeholder="0">
            </div>

            <!-- Observações (opcional) -->
            <div class="form-group">
                <label for="observacoes"><i class="fas fa-sticky-note"></i> Observações <small style="font-weight:400;color:var(--gray)">(opcional)</small>:</label>
                <input type="text" id="observacoes" name="observacoes" placeholder="Observações sobre esta contagem">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:8px;" id="btnRegistrar">
            <i class="fas fa-plus-circle"></i> Registrar Contagem
        </button>
    </form>
</div>

<!-- Cards de status - APENAS ADMIN -->
<?php if ($isAdmin && !empty($stats)): ?>
    <div class="cards-container">
        <div class="card">
            <h3>Total</h3>
            <div class="value"><?= $stats['total'] ?></div>
        </div>
        <div class="card" style="border-top-color:var(--success);">
            <h3 style="color:var(--success);">Concluídas</h3>
            <div class="value" style="color:var(--success);"><?= $stats['concluidas'] ?></div>
        </div>
        <div class="card" style="border-top-color:var(--danger);">
            <h3 style="color:var(--danger);">Divergentes</h3>
            <div class="value" style="color:var(--danger);"><?= $stats['divergentes'] ?></div>
        </div>
        <div class="card" style="border-top-color:var(--warning);">
            <h3 style="color:var(--warning);">Pendentes</h3>
            <div class="value" style="color:var(--warning);"><?= $stats['pendentes'] ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="form-container" style="padding:20px;">
        <form method="GET" action="?pagina=contagem" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="pagina" value="contagem">
            <div style="flex:1;min-width:230px;">
                <label style="font-size:12px;margin-bottom:4px;display:block;">Part Number:</label>
                <input type="text" name="partnumber" value="<?= htmlspecialchars($_GET['partnumber'] ?? '') ?>" placeholder="Filtrar...">
            </div>
            <div style="flex:1;min-width:230px;">
                <label style="font-size:12px;margin-bottom:4px;display:block;">Depósito:</label>
                <input type="text" name="deposito" value="<?= htmlspecialchars($_GET['deposito'] ?? '') ?>" placeholder="Filtrar...">
            </div>
            <!-- <div style="flex:1;min-width:140px;"> -->
            <!-- <label style="font-size:12px;margin-bottom:4px;display:block;">Status:</label>
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach (['primaria', 'concluida', 'divergente', 'terceira'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>>
                            </option>
                            <?= ucfirst($s) ?>
                    <?php endforeach; ?>http://localhost:8000/public/index.php?pagina=contagem
                </select> -->
            <!-- </div> -->
            <div>
                <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="?pagina=contagem" class="btn btn-sm" style="background:#eee;color:var(--gray);">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela de contagens -->
    <div class="table-container">
        <table>
            <style>
                /* Ajuste para mobile: 2 cards por linha */
                @media screen and (max-width: 600px) {
                    .cards-container {
                        display: grid !important;
                        grid-template-columns: 1fr 1fr;
                        gap: 12px;
                    }

                    .card {
                        margin-bottom: 0;
                        /* remove margem inferior extra, se houver */
                        width: 100% !important;
                        /* garante que ocupe a coluna inteira */
                    }
                }
            </style>
            <thead>
                <tr>
                    <th>Depósito</th>
                    <th>Part Number</th>
                    <th>Qtd. 1ª</th>
                    <th>Qtd. 2ª</th>
                    <th>Qtd. Final</th>
                    <th>Status</th>
                    <th>Contador</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>


                <?php if (empty($pagination['items'])): ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 9 : 8 ?>" style="text-align:center;padding:30px;color:var(--gray);">
                            <i class="fas fa-inbox"></i> Nenhuma contagem encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pagination['items'] as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['deposito']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c['partnumber']) ?></strong>
                                <?php if ($c['lote']): ?>
                                    <br><small style="color:var(--gray);">Lote: <?= htmlspecialchars($c['lote']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float)$c['quantidade_primaria'], 2, ',', '.') ?></td>
                            <td><?= $c['quantidade_secundaria'] !== null ? number_format((float)$c['quantidade_secundaria'], 2, ',', '.') : '—' ?></td>
                            <td>
                                <?php if ($c['quantidade_final'] !== null): ?>
                                    <strong style="color:var(--success);"><?= number_format((float)$c['quantidade_final'], 2, ',', '.') ?></strong>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($c['status']) ?>">
                                    <?= htmlspecialchars($c['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:13px;"><?= htmlspecialchars($c['usuario_nome'] ?? '—') ?></td>
                            <td style="font-size:12px;white-space:nowrap;">
                                <?= $c['data_contagem_primaria'] ? date('d/m/Y H:i', strtotime($c['data_contagem_primaria'])) : '—' ?>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <?php if ($c['status'] === 'primaria' || $c['status'] === 'divergente'): ?>
                                        <?php
                                        $btnLabel = $c['status'] === 'divergente' ? '3ª Contagem' : '2ª Contagem';
                                        $prim     = (float)$c['quantidade_primaria'];
                                        $sec      = (float)($c['quantidade_secundaria'] ?? 0);
                                        ?>
                                        <!-- <button class="btn btn-sm btn-outline"
                                            onclick="abrirModalTerceiraContagem(<?= $c['id'] ?>, <?= $prim ?>, <?= $sec ?>)">
                                            <i class="fas fa-balance-scale"></i> <?= $btnLabel ?>
                                        </button> -->
                                    <?php else: ?>
                                        <span style="color:var(--gray);font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Paginação -->
<?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination">
        <?php
        $base = '?pagina=contagem'
            . (($_GET['partnumber'] ?? '') ? '&partnumber=' . urlencode($_GET['partnumber']) : '')
            . (($_GET['deposito']   ?? '') ? '&deposito='   . urlencode($_GET['deposito'])   : '')
            . (($_GET['status']     ?? '') ? '&status='     . urlencode($_GET['status'])     : '');
        ?>
        <?php if ($pagination['page'] > 1): ?>
            <a href="<?= $base ?>&p=<?= $pagination['page'] - 1 ?>"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <?php if ($i === $pagination['page']): ?>
                <span class="current"><?= $i ?></span>
            <?php elseif (abs($i - $pagination['page']) <= 2 || $i === 1 || $i === $pagination['total_pages']): ?>
                <a href="<?= $base ?>&p=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
            <a href="<?= $base ?>&p=<?= $pagination['page'] + 1 ?>"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    const depositos = <?= json_encode(array_column($depositos, 'deposito')) ?>;
    const partnumbers = <?= json_encode(array_column($partnumbers, 'partnumber')) ?>;
</script>
<script>
    function setupAutocomplete(inputId, dropdownId, dataList, novoDivId = null) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        const novoDiv = novoDivId ? document.getElementById(novoDivId) : null;

        input.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            dropdown.innerHTML = '';

            if (!value) {
                dropdown.style.display = 'none';
                if (novoDiv) novoDiv.style.display = 'none';
                return;
            }

            const matches = dataList.filter(item =>
                item.toLowerCase().includes(value)
            );

            matches.forEach(item => {
                const option = document.createElement('div');
                option.className = 'autocomplete-item';
                option.textContent = item;

                option.onclick = () => {
                    input.value = item;
                    dropdown.style.display = 'none';
                    if (novoDiv) novoDiv.style.display = 'none';
                };

                dropdown.appendChild(option);
            });

            dropdown.style.display = matches.length ? 'block' : 'none';

            // Se não encontrou, mostrar campos extras
            if (novoDiv) {
                const existe = dataList.some(item =>
                    item.toLowerCase() === value
                );
                novoDiv.style.display = existe ? 'none' : 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    // Inicializar
    setupAutocomplete('depositoInput', 'depositoDropdown', depositos, 'novoDepositoDiv');
    setupAutocomplete('partnumberInput', 'pnDropdown', partnumbers, 'novoPnDiv');
</script>

<script>
    (function() {
        const input = document.getElementById("partnumberInput");
        const dropdown = document.getElementById("pnDropdown");
        const novoDiv = document.getElementById("novoPnDiv");

        if (!input || !dropdown) return;

        input.addEventListener("input", function() {
            const value = this.value.trim().toLowerCase();
            dropdown.innerHTML = "";

            if (!value) {
                dropdown.style.display = "none";
                novoDiv.style.display = "none";
                return;
            }

            const matches = partnumbers.filter(pn =>
                pn.toLowerCase().includes(value)
            );

            // Criar opções
            matches.slice(0, 10).forEach(pn => {
                const option = document.createElement("div");
                option.className = "autocomplete-item";
                option.textContent = pn;

                option.addEventListener("mousedown", function(e) {
                    e.preventDefault(); // evita perder o foco
                    input.value = pn;
                    dropdown.style.display = "none";
                    novoDiv.style.display = "none";
                });

                dropdown.appendChild(option);
            });

            dropdown.style.display = matches.length ? "block" : "none";

            // Mostrar campos de novo PN se não existir
            const existe = partnumbers.some(pn =>
                pn.toLowerCase() === value
            );

            novoDiv.style.display = existe ? "none" : "block";
        });

        // Fechar dropdown ao clicar fora
        document.addEventListener("click", function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = "none";
            }
        });
    })();
</script>

<script>
    input.addEventListener("input", function() {
        const value = this.value.trim().toLowerCase();
        dropdown.innerHTML = "";

        if (!value) {
            dropdown.style.display = "none";
            novoDiv.style.display = "none";
            return;
        }

        const matches = partnumbers.filter(pn =>
            pn.toLowerCase().includes(value)
        );

        matches.slice(0, 10).forEach(pn => {
            const option = document.createElement("div");
            option.className = "autocomplete-item";
            option.textContent = pn;

            option.addEventListener("mousedown", function(e) {
                e.preventDefault();
                input.value = pn;
                dropdown.style.display = "none";
                novoDiv.style.display = "none";
            });

            dropdown.appendChild(option);
        });

        dropdown.style.display = matches.length ? "block" : "none";

        const existe = partnumbers.some(pn =>
            pn.toLowerCase() === value
        );

        novoDiv.style.display = existe ? "none" : "block";
    });

    function toggleOutroDeposito(val) {
        const div = document.getElementById('novoDepositoDiv');
        const input = document.getElementById('novoDeposito');
        div.style.display = val === 'outro' ? 'block' : 'none';
        if (val === 'outro') {
            input.setAttribute('required', '');
        } else {
            input.removeAttribute('required');
        }
    }


    const depositos = <?= json_encode(array_column($depositos, 'deposito')) ?>;
    const partnumbers = <?= json_encode(array_column($partnumbers, 'partnumber')) ?>;

    function setupAutocomplete(inputId, dropdownId, dataList, novoDivId = null) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        const novoDiv = novoDivId ? document.getElementById(novoDivId) : null;

        if (!input || !dropdown) return;

        input.addEventListener('input', function() {
            const value = this.value.trim().toLowerCase();
            dropdown.innerHTML = '';

            if (!value) {
                dropdown.style.display = 'none';
                if (novoDiv) novoDiv.style.display = 'none';
                return;
            }

            const matches = dataList.filter(item =>
                item.toLowerCase().includes(value)
            );

            matches.slice(0, 10).forEach(item => {
                const option = document.createElement('div');
                option.className = 'autocomplete-item';
                option.textContent = item;

                option.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    input.value = item;
                    dropdown.style.display = 'none';
                    if (novoDiv) novoDiv.style.display = 'none';
                });

                dropdown.appendChild(option);
            });

            dropdown.style.display = matches.length ? 'block' : 'none';

            if (novoDiv) {
                const existe = dataList.some(item =>
                    item.toLowerCase() === value
                );
                novoDiv.style.display = existe ? 'none' : 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupAutocomplete('depositoInput', 'depositoDropdown', depositos, 'novoDepositoDiv');
        setupAutocomplete('partnumberInput', 'pnDropdown', partnumbers, 'novoPnDiv');

        // botão de submit
        const form = document.getElementById('formContagem');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('btnRegistrar');
                if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
                    return;
                }
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
            });
        }
    });
</script>

<?php require SRC_PATH . '/Views/layout/footer.php'; ?>