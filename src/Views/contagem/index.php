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

$isAdmin  = Security::isAdmin();
$msgClass = str_contains((string)$message, '✔')
    || str_contains((string)$message, 'registrada')
    || str_contains((string)$message, 'Somado')
    || str_contains((string)$message, 'CONVERGENTE')
    ? 'sucesso' : 'erro';
?>

<?php if (!empty($message)): ?>
    <div class="mensagem <?= $msgClass ?>" data-auto-hide>
        <i class="fas <?= $msgClass === 'sucesso' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- ============================================================
     FORMULÁRIO DE CONTAGEM
     ============================================================ -->
<div class="form-container">
    <h2 class="form-title">
        <i class="fas fa-clipboard-check" style="color:var(--secondary)"></i>
        Registrar Contagem
        <span style="font-size:13px;color:var(--gray);font-weight:400;margin-left:auto;display:flex;align-items:center;gap:10px;">
            <span>Inventário: <strong><?= htmlspecialchars($inventarioAtivo['codigo']) ?></strong></span>
            <button type="button" onclick="iniciarScannerQR()" class="btn btn-sm btn-secondary"
                title="Ler QR Code com a câmera">
                <i class="fas fa-qrcode"></i> Scan QR
            </button>
        </span>
    </h2>

    <form id="formContagem" method="POST" action="?pagina=contagem">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="acao_contagem" value="registrar">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="form-grid-2">

            <!-- Depósito -->
            <div class="form-group">
                <label for="depositoInput"><i class="fas fa-warehouse"></i> Depósito</label>
                <div class="autocomplete-container">
                    <input type="text" id="depositoInput" name="deposito" required
                        placeholder="Digite ou selecione o depósito" autocomplete="off">
                    <div id="depositoDropdown" class="autocomplete-dropdown" style="display:none;"></div>
                </div>
                <div id="novoDepositoDiv" style="display:none;margin-top:8px;">
                    <input type="text" name="nova_localizacao" placeholder="Localização (opcional)">
                </div>
            </div>

            <!-- Part Number -->
            <div class="form-group">
                <label for="partnumberInput"><i class="fas fa-barcode"></i> Part Number</label>
                <div class="autocomplete-container">
                    <input type="text" id="partnumberInput" name="partnumber" required
                        placeholder="Digite ou selecione o part number" autocomplete="off">
                    <div id="pnDropdown" class="autocomplete-dropdown" style="display:none;"></div>
                </div>
                <!-- Aviso PN encerrado -->
                <div id="erroPartNumberEncerrado" class="mensagem erro" style="display:none;margin-top:8px;padding:8px 12px;">
                    <i class="fas fa-ban"></i> Este partnumber já foi <strong>encerrado</strong>!
                </div>
                <!-- Aviso status da contagem existente -->
                <div id="avisoStatusContagem" style="display:none;margin-top:8px;padding:8px 12px;
                     border-radius:8px;font-size:13px;font-weight:600;border-left:4px solid var(--info);
                     background:#eff6ff;color:#1e40af;">
                </div>
                <div id="novoPnDiv" style="display:none;margin-top:8px;">
                    <input type="text" name="nova_descricao" placeholder="Descrição (opcional)">
                    <input type="text" name="nova_unidade" placeholder="Unidade (ex: UN, CX)" style="margin-top:8px;">
                </div>
            </div>

            <!-- Quantidade -->
            <div class="form-group">
                <label for="quantidade"><i class="fas fa-sort-numeric-up"></i> Quantidade</label>
                <input type="number" id="quantidade" name="quantidade" required
                    min="0.0001" step="0.0001" placeholder="0">
            </div>

            <!-- Observações -->
            <div class="form-group">
                <label for="observacoes">
                    <i class="fas fa-sticky-note"></i> Observações
                    <span style="font-weight:400;color:var(--gray);text-transform:none;">(opcional)</span>
                </label>
                <input type="text" id="observacoes" name="observacoes"
                    placeholder="Observações sobre esta contagem">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:8px;" id="btnRegistrar">
            <i class="fas fa-plus-circle"></i>
            <span class="btn-text">Registrar Contagem</span>
        </button>
    </form>
</div>

<!-- ============================================================
     ÁREA ADMIN: CARDS + FILTROS + TABELA
     ============================================================ -->
<?php if ($isAdmin && !empty($stats)): ?>

    <!-- Cards -->
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

    <!-- Legenda de cores -->
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;padding:12px 16px;
                background:white;border-radius:10px;box-shadow:var(--shadow);font-size:12px;font-weight:600;">
        <span style="color:#92400e;"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#fef3c7;border:1px solid #d97706;margin-right:5px;"></span>1ª contagem</span>
        <span style="color:#1e40af;"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#dbeafe;border:1px solid #3b82f6;margin-right:5px;"></span>Contagens iguais ✓</span>
        <span style="color:#92400e;"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#fffbeb;border:1px solid #d97706;margin-right:5px;"></span>Contagens divergentes</span>
        <span style="color:#991b1b;"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#fef2f2;border:1px solid #dc2626;margin-right:5px;"></span>Divergente final</span>
        <span style="color:#166534;"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#f0fdf4;border:1px solid #16a34a;margin-right:5px;"></span>Concluída ✓</span>
    </div>

    <!-- Filtros -->
    <div class="form-container" style="padding:16px;">
        <form method="GET" action="?pagina=contagem"
            style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="pagina" value="contagem">
            <div style="flex:1;min-width:200px;">
                <label style="font-size:11px;margin-bottom:4px;display:block;text-transform:uppercase;letter-spacing:.4px;">Part Number</label>
                <input type="text" name="partnumber"
                    value="<?= htmlspecialchars($_GET['partnumber'] ?? '') ?>" placeholder="Filtrar...">
            </div>
            <div style="flex:1;min-width:200px;">
                <label style="font-size:11px;margin-bottom:4px;display:block;text-transform:uppercase;letter-spacing:.4px;">Depósito</label>
                <input type="text" name="deposito"
                    value="<?= htmlspecialchars($_GET['deposito'] ?? '') ?>" placeholder="Filtrar...">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="?pagina=contagem" class="btn btn-ghost btn-sm">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Depósito</th>
                    <th>Part Number</th>
                    <th>1ª Contagem</th>
                    <th>2ª Contagem</th>
                    <th>3ª Contagem</th>
                    <th>Qtd. Final</th>
                    <th>Status</th>
                    <th>Contador</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pagination['items'])): ?>
                    <tr>
                        <td colspan="10" style="text-align:center;padding:32px;color:var(--gray);">
                            <i class="fas fa-inbox fa-2x" style="display:block;margin-bottom:8px;opacity:.4;"></i>
                            Nenhuma contagem encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    // Declarada UMA VEZ fora do foreach para evitar "Cannot redeclare"
                    function fmtQtd(?float $val, ?float $compare = null): string
                    {
                        if ($val === null) return '<span style="color:#cbd5e1">—</span>';
                        $num = number_format($val, 2, ',', '.');
                        if ($compare !== null) {
                            if (abs($val - $compare) < 0.0001) {
                                return "<span class='qtd-match'>{$num}<span class='qtd-icon'>✓</span></span>";
                            } else {
                                return "<span class='qtd-diff'>{$num}<span class='qtd-icon'>≠</span></span>";
                            }
                        }
                        return "<span class='qtd-cell'>{$num}</span>";
                    }
                    ?>
                    <?php foreach ($pagination['items'] as $c): ?>
                        <?php
                        $finalizado   = (bool)($c['finalizado'] ?? false);
                        $numContagens = (int)($c['numero_contagens_realizadas'] ?? 1);
                        $status       = $c['status'] ?? 'primaria';
                        $qtd1         = $c['quantidade_primaria']   !== null ? (float)$c['quantidade_primaria']   : null;
                        $qtd2         = $c['quantidade_secundaria'] !== null ? (float)$c['quantidade_secundaria'] : null;
                        $qtd3         = $c['quantidade_terceira']   !== null ? (float)$c['quantidade_terceira']   : null;
                        $qtdFinal     = $c['quantidade_final']      !== null ? (float)$c['quantidade_final']      : null;

                        // Detectar convergência entre 1ª e 2ª
                        $match12 = ($qtd2 !== null) && (abs($qtd1 - $qtd2) < 0.0001);

                        // Classe da linha
                        if ($finalizado) {
                            $trClass = 'linha-encerrado';
                        } elseif ($status === 'divergente') {
                            $trClass = 'linha-divergente';
                        } elseif ($status === 'concluida') {
                            $trClass = 'linha-match';
                        } elseif ($qtd2 !== null && $match12) {
                            $trClass = 'linha-match';
                        } elseif ($qtd2 !== null && !$match12) {
                            $trClass = 'linha-diff';
                        } else {
                            $trClass = 'linha-primaria';
                        }

                        // Badge status
                        if ($finalizado) {
                            $badge = '<span class="status-badge status-encerrado"><i class="fas fa-lock"></i> Encerrado</span>';
                        } else {
                            $badge = match ($status) {
                                'primaria'   => '<span class="status-badge status-primaria"><i class="fas fa-clock"></i> Em andamento</span>',
                                'secundaria' => '<span class="status-badge status-secundaria"><i class="fas fa-layer-group"></i> 2ª Contagem</span>',
                                'concluida'  => '<span class="status-badge status-concluida"><i class="fas fa-check-circle"></i> Concluída</span>',
                                'divergente' => '<span class="status-badge status-divergente"><i class="fas fa-exclamation-triangle"></i> Divergente</span>',
                                default      => '<span class="status-badge status-encerrado">' . htmlspecialchars($status) . '</span>',
                            };
                        }
                        ?>
                        <tr class="<?= $trClass ?>">
                            <td><strong><?= htmlspecialchars($c['deposito']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($c['partnumber']) ?></strong>
                                <?php if (!$finalizado): ?>
                                    <?php if ($numContagens == 2): ?>
                                        <span class="fase-badge fase-badge-2">2ª contagem</span>
                                    <?php elseif ($numContagens == 3): ?>
                                        <span class="fase-badge fase-badge-3">3ª contagem</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($c['lote']): ?>
                                    <br><small style="color:var(--gray);font-size:11px;">
                                        <i class="fas fa-tag"></i> <?= htmlspecialchars($c['lote']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="qtd-cell"><?= fmtQtd($qtd1) ?></td>
                            <td class="qtd-cell"><?= fmtQtd($qtd2, $qtd1) ?></td>
                            <td class="qtd-cell"><?= fmtQtd($qtd3, $qtd2 ?? $qtd1) ?></td>
                            <td>
                                <?php if ($qtdFinal !== null): ?>
                                    <strong style="color:var(--success);font-size:15px;">
                                        <?= number_format($qtdFinal, 2, ',', '.') ?>
                                    </strong>
                                <?php else: ?>
                                    <span style="color:#cbd5e1">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $badge ?></td>
                            <td style="font-size:13px;"><?= htmlspecialchars($c['usuario_nome'] ?? '—') ?></td>
                            <td style="font-size:12px;white-space:nowrap;color:var(--gray);">
                                <?= $c['data_contagem_primaria']
                                    ? date('d/m/y H:i', strtotime($c['data_contagem_primaria']))
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if ($finalizado): ?>
                                    <span style="color:var(--gray);font-size:12px;"><i class="fas fa-lock"></i></span>
                                <?php else: ?>
                                    <button class="btn-acao" id="btnAcao_<?= $c['id'] ?>"
                                        onclick="abrirAcaoModal(
                                    <?= (int)$c['id'] ?>,
                                    '<?= htmlspecialchars(addslashes($c['partnumber'])) ?>',
                                    '<?= htmlspecialchars(addslashes($c['deposito'])) ?>',
                                    <?= (int)$inventarioAtivo['id'] ?>,
                                    <?= $numContagens ?>,
                                    <?= $isAdmin ? 'true' : 'false' ?>
                                )">
                                        <i class="fas fa-ellipsis-h"></i> Ação
                                    </button>
                                <?php endif; ?>
                            </td>
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

<!-- ============================================================
     MODAL DE AÇÕES
     ============================================================ -->
<div id="acaoModal">
    <div id="modalContent">
        <h3><i class="fas fa-cog" style="color:var(--secondary)"></i> Ação</h3>
        <p id="modalSubtitle" style="color:var(--gray);font-size:13px;margin:-8px 0 16px;"></p>
        <div class="modal-actions">
            <button id="btnNovaContagem" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span class="btn-text">Liberar Próxima Contagem</span>
            </button>
            <button id="btnFinalizar" class="btn btn-danger">
                <i class="fas fa-lock"></i>
                <span class="btn-text">Encerrar Contagem</span>
            </button>
            <button onclick="fecharAcaoModal()" class="btn btn-ghost">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal QR Scanner -->
<div id="qrScannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);
     z-index:10000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:white;padding:24px;border-radius:12px;max-width:400px;width:100%;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;"><i class="fas fa-qrcode" style="color:var(--secondary)"></i> Scanner QR</h3>
            <button onclick="fecharScannerQR()" class="btn btn-ghost btn-sm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="qr-reader"></div>
    </div>
</div>

<script>
    // ============================================================
    // VARIÁVEIS INJETADAS PELO PHP
    // ============================================================
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const inventarioId = <?= (int)$inventarioAtivo['id'] ?>;
    const isAdminUser = <?= $isAdmin ? 'true' : 'false' ?>;
    const depositos = <?= json_encode(array_column($depositos, 'deposito')) ?>;
    const partnumbers = <?= json_encode(array_column($partnumbers, 'partnumber')) ?>;

    // ============================================================
    // AUTOCOMPLETE
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        setupAutocomplete('depositoInput', 'depositoDropdown', depositos, 'novoDepositoDiv');
        setupAutocomplete('partnumberInput', 'pnDropdown', partnumbers, 'novoPnDiv');

        const pnInput = document.getElementById('partnumberInput');
        const depInput = document.getElementById('depositoInput');

        if (pnInput) {
            pnInput.addEventListener('blur', verificarStatusPN);
            pnInput.addEventListener('input', () => {
                esconderAvisos();
                _cache = {};
            });
        }
        if (depInput) {
            depInput.addEventListener('blur', verificarStatusPN);
        }

        // Submit com loading
        const form = document.getElementById('formContagem');
        if (form) {
            form.addEventListener('submit', async function(e) {
                const pn = pnInput?.value.trim();
                const dep = depInput?.value.trim();

                if (pn && dep) {
                    const key = pn + '|' + dep;
                    let dados = _cache[key];

                    if (!dados) {
                        e.preventDefault();
                        const fd = new FormData();
                        fd.append('partnumber', pn);
                        fd.append('deposito', dep);
                        try {
                            const r = await fetch('?pagina=ajax&acao=verificar_finalizado', {
                                method: 'POST',
                                body: fd
                            });
                            dados = await r.json();
                            _cache[key] = dados;
                        } catch {
                            dados = {
                                finalizado: false
                            };
                        }

                        if (dados.finalizado) {
                            mostrarErroPNEncerrado();
                            return;
                        }
                        form.submit();
                        return;
                    }
                    if (dados.finalizado) {
                        e.preventDefault();
                        mostrarErroPNEncerrado();
                        return;
                    }
                }

                const btn = document.getElementById('btnRegistrar');
                if (btn) btnLoading(btn, true);
            });
        }
    });

    // ============================================================
    // VALIDAÇÃO PN + DEPÓSITO
    // ============================================================
    let _cache = {};

    async function verificarStatusPN() {
        const pn = document.getElementById('partnumberInput')?.value.trim();
        const dep = document.getElementById('depositoInput')?.value.trim();
        esconderAvisos();
        if (!pn || !dep) return;

        const key = pn + '|' + dep;
        if (!_cache[key]) {
            try {
                const fd = new FormData();
                fd.append('partnumber', pn);
                fd.append('deposito', dep);
                const r = await fetch('?pagina=ajax&acao=verificar_finalizado', {
                    method: 'POST',
                    body: fd
                });
                _cache[key] = await r.json();
            } catch {
                return;
            }
        }

        const dados = _cache[key];
        if (dados.finalizado) {
            mostrarErroPNEncerrado();
            document.getElementById('partnumberInput').value = '';
            document.getElementById('partnumberInput').focus();
            delete _cache[key];
            return;
        }

        if (dados.existe && dados.status) {
            mostrarAvisoStatus(dados);
        }
    }

    function mostrarErroPNEncerrado() {
        const el = document.getElementById('erroPartNumberEncerrado');
        if (el) el.style.display = 'flex';
    }

    function mostrarAvisoStatus(dados) {
        const el = document.getElementById('avisoStatusContagem');
        if (!el) return;

        const labels = {
            primaria: '1ª contagem registrada',
            secundaria: '2ª contagem registrada',
            concluida: 'Contagem concluída',
            divergente: 'Aguardando revisão'
        };
        const label = labels[dados.status] || dados.status;
        const podeNova = dados.pode_nova;

        let msg = `<i class="fas fa-info-circle"></i> ${label}`;
        if (podeNova) {
            msg = `<i class="fas fa-unlock"></i> <strong>Nova contagem liberada!</strong> Registre a quantidade — será somada à fase atual.`;
            el.style.borderColor = 'var(--success)';
            el.style.background = '#f0fdf4';
            el.style.color = '#166534';
        }
        el.innerHTML = msg;
        el.style.display = 'block';
    }

    function esconderAvisos() {
        document.getElementById('erroPartNumberEncerrado')?.style && (document.getElementById('erroPartNumberEncerrado').style.display = 'none');
        document.getElementById('avisoStatusContagem')?.style && (document.getElementById('avisoStatusContagem').style.display = 'none');
    }

    // ============================================================
    // MODAL DE AÇÕES
    // ============================================================
    let acaoAtual = null;

    function abrirAcaoModal(id, partnumber, deposito, invId, numContagens, isAdmin) {
        acaoAtual = {
            id,
            partnumber,
            deposito,
            invId,
            numContagens,
            isAdmin
        };

        const modal = document.getElementById('acaoModal');
        const content = document.getElementById('modalContent');
        const btnNova = document.getElementById('btnNovaContagem');
        const sub = document.getElementById('modalSubtitle');

        if (sub) sub.textContent = partnumber + ' · ' + deposito;

        if (btnNova) {
            if (!isAdmin || numContagens >= 3) {
                btnNova.style.display = 'none';
            } else {
                btnNova.style.display = 'flex';
                const proxima = numContagens + 1;
                btnNova.querySelector('.btn-text').textContent = `Liberar ${proxima}ª Contagem`;
            }
        }

        modal.style.display = 'flex';
        requestAnimationFrame(() => {
            modal.style.opacity = '1';
            content.style.transform = 'scale(1)';
        });
    }

    function fecharAcaoModal() {
        const modal = document.getElementById('acaoModal');
        const content = document.getElementById('modalContent');
        modal.style.opacity = '0';
        content.style.transform = 'scale(0.9)';
        setTimeout(() => {
            modal.style.display = 'none';
            acaoAtual = null;
        }, 280);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btnNova = document.getElementById('btnNovaContagem');
        const btnFinalizar = document.getElementById('btnFinalizar');
        const modal = document.getElementById('acaoModal');

        btnNova?.addEventListener('click', function() {
            if (!acaoAtual) return;
            const id = acaoAtual.id;
            const n = acaoAtual.numContagens;
            fecharAcaoModal();
            if (n === 1) executarLiberar(id, 2);
            else if (n === 2) executarLiberar(id, 3);
            else showToast('Número máximo de contagens já atingido.', 'aviso');
        });

        btnFinalizar?.addEventListener('click', function() {
            if (!acaoAtual) return;
            const {
                id,
                partnumber
            } = acaoAtual;
            fecharAcaoModal();
            executarEncerrar(id, partnumber);
        });

        modal?.addEventListener('click', e => {
            if (e.target === modal) fecharAcaoModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fecharAcaoModal();
        });
    });

    // ============================================================
    // AÇÕES AJAX COM FEEDBACK VISUAL
    // ============================================================
    function executarLiberar(contagemId, fase) {
        const nomeFase = fase === 2 ? 'SEGUNDA' : 'TERCEIRA';

        showConfirm(
            `Deseja liberar a <strong>${nomeFase} contagem</strong> para este item?<br>
         <small style="color:var(--gray)">O operador poderá registrar a próxima contagem.</small>`,
            () => {
                const acao = fase === 2 ? 'liberar_segunda' : 'liberar_terceira';
                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('contagem_id', contagemId);

                fetch(`?pagina=ajax&acao=${acao}`, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            showToast(d.message, 'sucesso');
                            setTimeout(() => location.reload(), 1400);
                        } else {
                            showToast(d.message || 'Erro ao liberar contagem.', 'erro');
                        }
                    })
                    .catch(() => showToast('Erro de comunicação com o servidor.', 'erro'));
            }
        );
    }

    function executarEncerrar(contagemId, partnumber) {
        showConfirm(
            `Encerrar a contagem de <strong>${partnumber}</strong>?<br>
         <small style="color:var(--gray)">Esta ação não pode ser desfeita. Nenhuma nova contagem será aceita para este item.</small>`,
            () => {
                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('contagem_id', contagemId);

                fetch('?pagina=ajax&acao=finalizar_contagem', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            showToast(d.message, 'sucesso');
                            setTimeout(() => location.reload(), 1400);
                        } else {
                            showToast(d.message || 'Erro ao encerrar.', 'erro');
                        }
                    })
                    .catch(() => showToast('Erro de comunicação com o servidor.', 'erro'));
            }
        );
    }
</script>
<?php require SRC_PATH . '/Views/layout/footer.php'; ?>