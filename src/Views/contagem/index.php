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
$msgClass = str_contains((string) $message, 'sucesso')
    || str_contains((string) $message, 'registrada')
    || str_contains((string) $message, 'atualizada')
    || str_contains((string) $message, 'finalizada')
    || str_contains((string) $message, '✔')
    ? 'sucesso' : 'erro';

// Verificar se há alguma nova contagem ativa na sessão (para indicar ao admin)
// $novasContagensAtivas = [];
// foreach ($_SESSION as $key => $val) {
//     if (str_starts_with($key, 'nova_contagem_')) {
//         $novasContagensAtivas[$key] = $val;
//     }
// }
// ?>

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
                    <div id="depositoDropdown" class="autocomplete-dropdown" style="display:none;"></div>
                </div>
                <div id="novoDepositoDiv" style="display:none;margin-top:10px;">
                    <input type="text" name="nova_localizacao" placeholder="Localização (opcional)">
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
                <!-- Aviso de PN encerrado -->
                <div id="erroPartNumberEncerrado" style="display:none; margin-top:6px;
                     padding:8px 12px; background:#f8d7da; border:1px solid #f5c6cb;
                     border-radius:6px; color:#721c24; font-size:13px; font-weight:600;">
                    ⚠️ Este partnumber já foi ENCERRADO! Não é possível fazer novas contagens.
                </div>
                <!-- Aviso de nova contagem ativa -->
                <div id="avisoNovaContagem" style="display:none; margin-top:6px;
                     padding:8px 12px; background:#d1ecf1; border:1px solid #bee5eb;
                     border-radius:6px; color:#0c5460; font-size:13px; font-weight:600;">
                    🔄 Nova contagem ativada para este PN — registre a quantidade normalmente.
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

    <style>
        @media screen and (max-width: 600px) {
            .cards-container {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .card {
                margin-bottom: 0;
                width: 100% !important;
            }
        }
    </style>

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
            <thead>
                <tr>
                    <th>Depósito</th>
                    <th>Part Number</th>
                    <th>Qtd. 1ª</th>
                    <th>Qtd. 2ª</th>
                    <th>3ª Contagem</th>
                    <th>Qtd. Final</th>
                    <th>Status</th>
                    <th>Contador</th>
                    <th>Data</th>
                    <th style="min-width:180px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pagination['items'])): ?>
                    <tr>
                        <td colspan="10" style="text-align:center;padding:30px;color:var(--gray);">
                            <i class="fas fa-inbox"></i> Nenhuma contagem encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pagination['items'] as $c): ?>
                        <?php
                        $finalizado   = (bool) ($c['finalizado'] ?? false);
                        $numContagens = (int) ($c['numero_contagens_realizadas'] ?? 1);

                        // Cor de fundo da linha
                        if ($finalizado) {
                            $corLinha = 'opacity:0.6; background-color:#F8F9FA;';
                        } else {
                            $corLinha = match ($c['status']) {
                                'primaria'   => 'background-color:#FFF3CD; border-left:4px solid #FFC107;',
                                'divergente' => 'background-color:#F8D7DA; border-left:4px solid #DC3545;',
                                'concluida'  => 'background-color:#D4EDDA; border-left:4px solid #28A745;',
                                default      => '',
                            };
                        }

                        // Badge de status
                        if ($finalizado) {
                            $badge = '<span style="background:#6c757d;color:#fff;padding:3px 9px;border-radius:4px;font-size:11px;font-weight:600;">🔒 Encerrado</span>';
                        } else {
                            $badge = match ($c['status']) {
                                'primaria'   => '<span style="background:#FFC107;color:#000;padding:3px 9px;border-radius:4px;font-size:11px;font-weight:600;">🟠 Em Andamento</span>',
                                'concluida'  => '<span style="background:#28A745;color:#fff;padding:3px 9px;border-radius:4px;font-size:11px;font-weight:600;">🟢 Concluída</span>',
                                'divergente' => '<span style="background:#DC3545;color:#fff;padding:3px 9px;border-radius:4px;font-size:11px;font-weight:600;">🔴 Divergente</span>',
                                default      => '<span style="background:#6c757d;color:#fff;padding:3px 9px;border-radius:4px;font-size:11px;">' . htmlspecialchars($c['status']) . '</span>',
                            };
                        }

                        // Verificar se nova contagem está ativa na sessão para este item
                        $sessionKey      = 'nova_contagem_' . md5($inventarioAtivo['id'] . '|' . $c['partnumber'] . '|' . $c['deposito']);
                        $novaContagemAtiva = isset($_SESSION[$sessionKey]) && (int)$_SESSION[$sessionKey] === (int)$c['id'];
                        ?>
                        <tr style="<?= $corLinha ?>">
                            <td><?= htmlspecialchars($c['deposito']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c['partnumber']) ?></strong>
                                <?php if ($novaContagemAtiva): ?>
                                    <br><small style="color:#0c5460;background:#d1ecf1;padding:1px 6px;border-radius:3px;font-size:10px;">🔄 aguardando <?= $numContagens + 1 ?>ª contagem</small>
                                <?php endif; ?>
                                <?php if ($c['lote']): ?>
                                    <br><small style="color:var(--gray);">Lote: <?= htmlspecialchars($c['lote']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float)$c['quantidade_primaria'], 2, ',', '.') ?></td>
                            <td><?= $c['quantidade_secundaria'] !== null ? number_format((float)$c['quantidade_secundaria'], 2, ',', '.') : '—' ?></td>
                            <td><?= $c['quantidade_terceira']  !== null ? number_format((float)$c['quantidade_terceira'],  2, ',', '.') : '—' ?></td>
                            <td>
                                <?php if ($c['quantidade_final'] !== null): ?>
                                    <strong style="color:var(--success);"><?= number_format((float)$c['quantidade_final'], 2, ',', '.') ?></strong>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= $badge ?></td>
                            <td style="font-size:13px;"><?= htmlspecialchars($c['usuario_nome'] ?? '—') ?></td>
                            <td style="font-size:12px;white-space:nowrap;">
                                <?= $c['data_contagem_primaria'] ? date('d/m/Y H:i', strtotime($c['data_contagem_primaria'])) : '—' ?>
                            <td style="white-space:nowrap;">
                                <?php if ($finalizado): ?>
                                    <span style="color:#6c757d;font-size:12px;">🔒 Encerrado</span>
                                <?php else: ?>
                                    <button
                                        onclick="abrirAcaoModal(
                <?= $c['id'] ?>,
                '<?= htmlspecialchars(addslashes($c['partnumber'])) ?>',
                '<?= htmlspecialchars(addslashes($c['deposito'])) ?>',
                <?= $inventarioAtivo['id'] ?>,
                <?= $numContagens ?>,
                <?= $isAdmin ? 'true' : 'false' ?>
            )"
                                        style="padding:6px 10px; border:none; border-radius:6px;
                   background:#0d6efd; color:#fff; cursor:pointer;
                   font-size:12px; font-weight:600;">
                                        Ação
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

<div id="acaoModal" style="
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s ease;
">
    <div id="modalContent" style="
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        width: 300px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        transform: scale(0.9);
        transition: transform 0.3s ease, opacity 0.3s ease;
    ">
        <h3 style="margin-top: 0;">Escolha a Ação</h3>

        <button id="btnNovaContagem" style="
            margin-top: 2vh;
            width: 100%;
            margin-bottom: 8px;
            padding: 10px;
            border: none;
            border-radius: 6px;
            background: #568ddf;
            color: #fff;
            cursor: pointer;
            transition: background 0.3s ease;
        " onmouseover="this.style.background='#568ddf'" onmouseout="this.style.background='rgb(11, 96, 224)'">
            ➕ Nova Contagem
        </button>

        <button id="btnFinalizar" style="
            width: 100%;
            margin-bottom: 8px;
            padding: 10px;
            border: none;
            border-radius: 6px;
            background: #de4251;
            color: #fff;
            cursor: pointer;
            transition: background 0.3s ease;
        " onmouseover="this.style.background='#de4251'" onmouseout="this.style.background='rgb(199, 27, 44)'">
            🔒 Finalizar Contagem
        </button>

        <button onclick="fecharAcaoModal()" style="
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #c3c3c3;
            cursor: pointer;
            transition: background 0.3s ease;
        " onmouseover="this.style.background='#ccc'" onmouseout="this.style.background='rgb(236, 236, 236)'">
            Cancelar
        </button>
    </div>
</div>
<script>
    // ============================================================
    // VARIÁVEIS GLOBAIS (INJETADAS PELO PHP)
    // ============================================================
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const inventarioId = <?= (int) $inventarioAtivo['id'] ?>;

    // AUTOCOMPLETE: arrays simples com os nomes
    const depositos = <?= json_encode(array_column($depositos, 'deposito')) ?>;
    const partnumbers = <?= json_encode(array_column($partnumbers, 'partnumber')) ?>;


    // ============================================================
    // AUTOCOMPLETE (DEPÓSITO E PART NUMBER)
    // ============================================================
    function setupAutocomplete(inputId, dropdownId, dataList, novoDivId = null) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        const novoDiv = novoDivId ? document.getElementById(novoDivId) : null;

        if (!input || !dropdown) return;

        input.addEventListener('input', function() {
            const value = this.value.trim().toLowerCase();
            dropdown.innerHTML = '';
            esconderAvisos();

            if (!value) {
                dropdown.style.display = 'none';
                if (novoDiv) novoDiv.style.display = 'none';
                return;
            }

            const matches = dataList.filter(item => item.toLowerCase().includes(value));

            matches.slice(0, 10).forEach(item => {
                const option = document.createElement('div');
                option.className = 'autocomplete-item';
                option.textContent = item;
                option.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    input.value = item;
                    dropdown.style.display = 'none';
                    if (novoDiv) novoDiv.style.display = 'none';
                    input.dispatchEvent(new Event('blur'));
                });
                dropdown.appendChild(option);
            });

            dropdown.style.display = matches.length ? 'block' : 'none';

            if (novoDiv) {
                const existe = dataList.some(item => item.toLowerCase() === value);
                novoDiv.style.display = existe ? 'none' : 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    function esconderAvisos() {
        const erro = document.getElementById('erroPartNumberEncerrado');
        const aviso = document.getElementById('avisoNovaContagem');
        if (erro) erro.style.display = 'none';
        if (aviso) aviso.style.display = 'none';
    }

    // ============================================================
    // VALIDAÇÃO AO PERDER O FOCO (BLUR)
    // ============================================================
    let _validacaoCache = {};

    async function validarCombinacaoPN() {
        const pn = document.getElementById('partnumberInput').value.trim();
        const dep = document.getElementById('depositoInput').value.trim();

        esconderAvisos();
        if (!pn || !dep) return;

        const cacheKey = pn + '|' + dep;
        let dados = _validacaoCache[cacheKey];

        if (!dados) {
            try {
                const fd = new FormData();
                fd.append('partnumber', pn);
                fd.append('deposito', dep);
                const r = await fetch('?pagina=ajax&acao=verificar_finalizado', {
                    method: 'POST',
                    body: fd
                });
                dados = await r.json();
                _validacaoCache[cacheKey] = dados;
            } catch (e) {
                return;
            }
        }

        if (dados.finalizado) {
            const erroDiv = document.getElementById('erroPartNumberEncerrado');
            if (erroDiv) erroDiv.style.display = 'block';
            document.getElementById('partnumberInput').value = '';
            document.getElementById('partnumberInput').focus();
            delete _validacaoCache[cacheKey];
            return;
        }

        // Verificar se há nova contagem ativa na sessão
        if (dados.existe && (dados.status === 'primaria' || dados.status === 'divergente')) {
            try {
                const fd2 = new FormData();
                fd2.append('partnumber', pn);
                fd2.append('deposito', dep);
                fd2.append('inventario_id', inventarioId);
                const r2 = await fetch('?pagina=ajax&acao=verificar_sessao_contagem', {
                    method: 'POST',
                    body: fd2
                });
                if (r2.ok) {
                    const d2 = await r2.json();
                    if (d2.ativa) {
                        const avisoDiv = document.getElementById('avisoNovaContagem');
                        if (avisoDiv) avisoDiv.style.display = 'block';
                    }
                }
            } catch (e) {
                // silencioso
            }
        }
    }

    // ============================================================
    // INICIALIZAÇÃO DO AUTOCOMPLETE E VALIDAÇÃO
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializa autocomplete
        setupAutocomplete('depositoInput', 'depositoDropdown', depositos, 'novoDepositoDiv');
        setupAutocomplete('partnumberInput', 'pnDropdown', partnumbers, 'novoPnDiv');

        const pnInput = document.getElementById('partnumberInput');
        const depInput = document.getElementById('depositoInput');

        if (pnInput) {
            pnInput.addEventListener('blur', validarCombinacaoPN);
            pnInput.addEventListener('input', function() {
                esconderAvisos();
                _validacaoCache = {};
            });
        }
        if (depInput) {
            depInput.addEventListener('blur', validarCombinacaoPN);
        }

        // Submit: verificar encerrado antes de enviar
        const form = document.getElementById('formContagem');
        if (form) {
            form.addEventListener('submit', async function(e) {
                const pn = pnInput.value.trim();
                const dep = depInput.value.trim();

                if (pn && dep) {
                    const cacheKey = pn + '|' + dep;
                    let dados = _validacaoCache[cacheKey];
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
                            _validacaoCache[cacheKey] = dados;
                        } catch (_) {
                            dados = {
                                finalizado: false
                            };
                        }

                        if (dados.finalizado) {
                            document.getElementById('erroPartNumberEncerrado').style.display = 'block';
                            pnInput.value = '';
                            pnInput.focus();
                            return;
                        }
                        form.submit();
                        return;
                    }

                    if (dados.finalizado) {
                        e.preventDefault();
                        document.getElementById('erroPartNumberEncerrado').style.display = 'block';
                        pnInput.value = '';
                        pnInput.focus();
                        return;
                    }
                }

                const btn = document.getElementById('btnRegistrar');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
            });
        }
    });

    // ============================================================
    // MODAL DE AÇÕES (CORRIGIDO)
    // ============================================================
    let acaoAtual = null;

    function abrirAcaoModal(id, partnumber, deposito, inventarioId, numContagens, isAdmin) {
        acaoAtual = {
            id: id,
            partnumber: partnumber,
            deposito: deposito,
            inventarioId: inventarioId,
            numContagens: numContagens,
            isAdmin: isAdmin
        };

        const modal = document.getElementById('acaoModal');
        const modalContent = document.getElementById('modalContent');
        const btnNova = document.getElementById('btnNovaContagem');

        // Mostrar/esconder botão de nova contagem conforme fase e permissão
        if (btnNova) {
            if (!isAdmin || numContagens >= 3) {
                // Não-admin ou já está na 3ª contagem: esconde botão
                btnNova.style.display = 'none';
            } else {
                btnNova.style.display = 'block';
                const proxima = numContagens + 1;
                btnNova.textContent = '➕ Liberar ' + proxima + 'ª Contagem';
            }
        }

        modal.style.display = 'flex';
        setTimeout(() => {
            modal.style.opacity = '1';
            modalContent.style.transform = 'scale(1)';
        }, 10);
    }

    function fecharAcaoModal() {
        const modal = document.getElementById('acaoModal');
        const modalContent = document.getElementById('modalContent');

        modal.style.opacity = '0';
        modalContent.style.transform = 'scale(0.9)';
        setTimeout(() => {
            modal.style.display = 'none';
            acaoAtual = null;
        }, 300);
    }

    // ============================================================
    // LISTENERS DO MODAL
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        const btnNova = document.getElementById('btnNovaContagem');
        const btnFinalizar = document.getElementById('btnFinalizar');
        const modal = document.getElementById('acaoModal');

        if (btnNova) {
            btnNova.addEventListener('click', function() {
                if (!acaoAtual) return;
                fecharAcaoModal();
                // Decide qual fase liberar com base no número de contagens atuais
                if (acaoAtual.numContagens === 1) {
                    liberarSegundaContagem(acaoAtual.id);
                } else if (acaoAtual.numContagens === 2) {
                    liberarTerceiraContagem(acaoAtual.id);
                } else {
                    alert('Número máximo de contagens já atingido.');
                }
            });
        }

        if (btnFinalizar) {
            btnFinalizar.addEventListener('click', function() {
                if (!acaoAtual) return;
                fecharAcaoModal();
                confirmarEncerramento(
                    acaoAtual.id,
                    acaoAtual.partnumber
                );
            });
        }

        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    fecharAcaoModal();
                }
            });
        }
    });

    function liberarSegundaContagem(contagemId) {
        if (!confirm('Deseja liberar a SEGUNDA contagem para este item?')) return;

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('contagem_id', contagemId);

        fetch('?pagina=ajax&acao=liberar_segunda', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(d => {
                alert(d.success ? '✅ ' + d.message : '❌ ' + d.message);
                if (d.success) location.reload();
            })
            .catch(e => alert('Erro de comunicação: ' + e));
    }

    function liberarTerceiraContagem(contagemId) {
        if (!confirm('Deseja liberar a TERCEIRA contagem para este item?')) return;

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('contagem_id', contagemId);

        fetch('?pagina=ajax&acao=liberar_terceira', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(d => {
                alert(d.success ? '✅ ' + d.message : '❌ ' + d.message);
                if (d.success) location.reload();
            })
            .catch(e => alert('Erro de comunicação: ' + e));
    }

    function finalizarContagem(contagemId) {
        if (!confirm('FINALIZAR? Não pode ser desfeito!')) return;

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('contagem_id', contagemId);

        fetch('?pagina=ajax&acao=finalizar_contagem', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(d => {
                alert(d.success ? '✅ ' + d.message : '❌ ' + d.message);
                if (d.success) location.reload();
            })
            .catch(e => alert('Erro de comunicação: ' + e));
    }
    // ============================================================
    // FUNÇÃO CONFIRMAR ENCERRAMENTO
    // ============================================================
    async function confirmarEncerramento(contagemId, partnumber) {
        const confirmado = confirm(
            '⚠️ ENCERRAR CONTAGEM\n\n' +
            'Partnumber: "' + partnumber + '"\n\n' +
            'Após encerrar:\n' +
            '• Nenhuma nova contagem poderá ser registrada para este item\n' +
            '• O operador verá uma mensagem de erro se tentar registrá-lo\n\n' +
            'Deseja continuar?'
        );
        if (!confirmado) return;

        try {
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('contagem_id', contagemId);

            const r = await fetch('?pagina=ajax&acao=finalizar_contagem', {
                method: 'POST',
                body: fd
            });
            const data = await r.json();

            if (data.success) {
                alert('🔒 ' + data.message);
                location.reload();
            } else {
                alert('⚠ ' + (data.message || 'Erro ao encerrar contagem.'));
            }
        } catch (err) {
            console.error(err);
            alert('Erro de comunicação com o servidor.');
        }
    }
</script>