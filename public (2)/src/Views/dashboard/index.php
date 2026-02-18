<?php
/**
 * @var string   $message
 * @var string   $csrfToken
 * @var ?array   $inventario
 * @var array    $stats
 */
use App\Core\Security;

$pageTitle = 'Dashboard — Sistema de Inventário';
require SRC_PATH . '/Views/layout/header.php';

$msgClass = str_contains((string) $message, 'sucesso') ? 'sucesso' : 'erro';
?>

<link rel="icon" type="image/png" href="/public/assets/Inserir um subtítulo.png">
<div class="form-container">
    <h2 class="form-title">
        <i class="fas fa-tachometer-alt"></i> Dashboard
        <?php if ($inventario): ?>
            <span style="font-size:13px;color:var(--gray);font-weight:400;margin-left:auto;">
                Inventário ativo: <strong><?= htmlspecialchars($inventario['codigo']) ?></strong>
            </span>
        <?php endif; ?>
    </h2>

    <?php if (!empty($message)): ?>
        <div class="mensagem <?= $msgClass ?>" data-auto-hide><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Status do inventário -->
    <?php if ($inventario): ?>
        <div class="form-container" style="background:linear-gradient(to right,#f8fff9,white);border-left:5px solid var(--success);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="color:var(--success);margin-bottom:8px;"><i class="fas fa-play-circle"></i> Inventário em Andamento</h3>
                    <p style="color:var(--gray);font-size:14px;">
                        <strong>Código:</strong> <?= htmlspecialchars($inventario['codigo']) ?> &nbsp;&bull;&nbsp;
                        <strong>Data:</strong> <?= date('d/m/Y', strtotime($inventario['data_inicio'])) ?> &nbsp;&bull;&nbsp;
                        <strong>Descrição:</strong> <?= htmlspecialchars($inventario['descricao']) ?>
                    </p>
                </div>
                <span style="background:var(--success);color:white;padding:7px 16px;border-radius:20px;font-weight:700;font-size:12px;">
                    <i class="fas fa-circle"></i> ABERTO
                </span>
            </div>
        </div>
    <?php else: ?>
        <div class="form-container" style="background:linear-gradient(to right,#fff8f8,white);border-left:5px solid var(--danger);">
            <div style="text-align:center;padding:20px;">
                <h3 style="color:var(--danger);margin-bottom:10px;"><i class="fas fa-pause-circle"></i> Nenhum Inventário Ativo</h3>
                <p style="color:var(--gray);">
                    <?= Security::isAdmin()
                        ? 'Crie um novo inventário para iniciar as contagens.'
                        : 'Aguarde o administrador iniciar um inventário.' ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cards de estatísticas -->
    <?php if ($inventario && !empty($stats)): ?>
        <div class="cards-container">
            <div class="card">
                <h3><i class="fas fa-clipboard-list"></i> Total de Contagens</h3>
                <div class="value"><?= $stats['total'] ?></div>
                <p style="color:var(--gray);font-size:12px;margin-top:5px;">registradas</p>
            </div>
            <div class="card" style="border-top-color:var(--success);">
                <h3><i class="fas fa-check-circle"></i> Concluídas</h3>
                <div class="value" style="color:var(--success);"><?= $stats['concluidas'] ?></div>
                <p style="color:var(--gray);font-size:12px;margin-top:5px;">validadas</p>
            </div>
            <div class="card" style="border-top-color:var(--danger);">
                <h3><i class="fas fa-exclamation-triangle"></i> Divergentes</h3>
                <div class="value" style="color:var(--danger);"><?= $stats['divergentes'] ?></div>
                <p style="color:var(--gray);font-size:12px;margin-top:5px;">necessitam atenção</p>
            </div>
            <div class="card" style="border-top-color:var(--warning);">
                <h3><i class="fas fa-clock"></i> Pendentes</h3>
                <div class="value" style="color:var(--warning);"><?= $stats['pendentes'] ?></div>
                <p style="color:var(--gray);font-size:12px;margin-top:5px;">aguardando 2ª contagem</p>
            </div>
            <?php if (($stats['terceiras'] ?? 0) > 0): ?>
            <div class="card" style="border-top-color:var(--info);">
                <h3><i class="fas fa-users"></i> 3ª Contagem</h3>
                <div class="value" style="color:var(--info);"><?= $stats['terceiras'] ?></div>
                <p style="color:var(--gray);font-size:12px;margin-top:5px;">aguardando 3ª contagem</p>
            </div>
            <?php endif; ?>
            <div class="card" style="border-top-color:var(--secondary);">
                <h3><i class="fas fa-barcode"></i> Part Numbers</h3>
                <div class="value"><?= $stats['partnumbers'] ?></div>
                <p style="color:var(--gray);font-size:12px;margin-top:5px;">distintos</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ações de admin -->
    <?php if (Security::isAdmin()): ?>
        <div class="form-container">
            <h3 class="form-title"><i class="fas fa-cog"></i> Gerenciar Inventário</h3>

            <?php if (!$inventario): ?>
                <!-- Criar inventário -->
                <form method="POST" action="?pagina=dashboard" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="acao_inventario" value="criar">

                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;flex-wrap:wrap;" class="form-grid">
                        <div class="form-group">
                            <label for="data_inicio"><i class="fas fa-calendar"></i> Data do Inventário:</label>
                            <input type="date" id="data_inicio" name="data_inicio" required
                                   value="<?= date('Y-m-d') ?>"
                                   min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label for="descricao"><i class="fas fa-file-alt"></i> Descrição:</label>
                            <input type="text" id="descricao" name="descricao" required
                                   placeholder="Ex: Inventário Trimestral — Setor A">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"
                            data-loading-text="Criando..." data-success-text="Criado!"
                            data-btn-anim="spinner">
                        <span class="btn-text"><i class="fas fa-plus-circle"></i> Criar Novo Inventário</span>
                    </button>
                </form>

            <?php else: ?>
                <!-- Fechar inventário + exportar -->
                <div style="background:#f8f9fa;padding:18px;border-radius:var(--border-r);margin-bottom:20px;display:grid;gap:8px;font-size:14px;">
                    <?php foreach ([
                        ['var(--success)', 'Concluídas',   $stats['concluidas']  ?? 0],
                        ['var(--danger)',  'Divergentes',   $stats['divergentes'] ?? 0],
                        ['var(--warning)', 'Pendentes',    $stats['pendentes']   ?? 0],
                    ] as [$cor, $label, $val]): ?>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:10px;height:10px;border-radius:50%;background:<?= $cor ?>;flex-shrink:0;"></div>
                        <span><?= $label ?>: <strong><?= $val ?></strong></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <form method="POST" action="?pagina=dashboard"
                          onsubmit="return confirmar('Fechar este inventário?\n\nApós fechar, novas contagens não poderão ser registradas.')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="acao_inventario" value="fechar">
                        <input type="hidden" name="inventario_id"   value="<?= $inventario['id'] ?>">
                        <button type="submit" class="btn btn-danger"
                                data-loading-text="Fechando..." data-success-text="Fechado!"
                                data-btn-anim="pulse">
                            <span class="btn-text"><i class="fas fa-lock"></i> Fechar Inventário</span>
                        </button>
                    </form>

                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle" type="button">
                            <i class="fas fa-file-export"></i> Exportar <i class="fas fa-chevron-down" style="font-size:11px;"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="?pagina=exportar&inventario_id=<?= $inventario['id'] ?>&formato=xlsx">
                                <i class="fas fa-file-excel" style="color:var(--success);"></i> Excel (XLSX)
                            </a>
                            <a href="?pagina=exportar&inventario_id=<?= $inventario['id'] ?>&formato=csv">
                                <i class="fas fa-file-csv"   style="color:var(--secondary);"></i> CSV
                            </a>
                            <a href="?pagina=exportar&inventario_id=<?= $inventario['id'] ?>&formato=txt">
                                <i class="fas fa-file-alt"   style="color:var(--gray);"></i> TXT
                            </a>
                        </div>
                    </div>

                    <a href="?pagina=contagem" class="btn btn-secondary">
                        <i class="fas fa-clipboard-check"></i> Ver Contagens
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require SRC_PATH . '/Views/layout/footer.php'; ?>
