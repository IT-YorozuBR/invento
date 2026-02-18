<?php
/**
 * @var array  $data      { items, page, total_pages, total }
 * @var string $message
 */
$pageTitle = 'Inventários Concluídos';
require SRC_PATH . '/Views/layout/header.php';
?>
<link rel="icon" type="image/png" href="/public/assets/Inserir um subtítulo.png">

<div class="form-container">
    <h2 class="form-title">
        <i class="fas fa-history"></i> Inventários Concluídos
        <span style="font-size:13px;color:var(--gray);font-weight:400;margin-left:auto;">
            <?= $data['total'] ?> registro(s)
        </span>
    </h2>

    <?php if (!empty($message)): ?>
        <div class="mensagem <?= str_contains($message,'sucesso') ? 'sucesso' : 'erro' ?>" data-auto-hide>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($data['items'])): ?>
        <div style="text-align:center;padding:40px;color:var(--gray);">
            <i class="fas fa-inbox" style="font-size:40px;margin-bottom:15px;display:block;"></i>
            Nenhum inventário concluído ainda.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descrição</th>
                        <th>Início</th>
                        <th>Fechamento</th>
                        <th>Administrador</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['items'] as $inv): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($inv['codigo']) ?></strong></td>
                        <td><?= htmlspecialchars($inv['descricao'] ?? '—') ?></td>
                        <td><?= $inv['data_inicio'] ? date('d/m/Y', strtotime($inv['data_inicio'])) : '—' ?></td>
                        <td><?= $inv['data_fim']    ? date('d/m/Y', strtotime($inv['data_fim']))    : '—' ?></td>
                        <td><?= htmlspecialchars($inv['admin_nome'] ?? '—') ?></td>
                        <td>
                            <span class="status-badge <?= $inv['status'] === 'fechado' ? 'status-concluida' : 'status-divergente' ?>">
                                <?= htmlspecialchars($inv['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline dropdown-toggle">
                                    <i class="fas fa-file-export"></i> Exportar
                                </button>
                                <div class="dropdown-menu">
                                    <a href="?pagina=exportar&inventario_id=<?= $inv['id'] ?>&formato=xlsx">
                                        <i class="fas fa-file-excel" style="color:var(--success);"></i> XLSX
                                    </a>
                                    <a href="?pagina=exportar&inventario_id=<?= $inv['id'] ?>&formato=csv">
                                        <i class="fas fa-file-csv"   style="color:var(--secondary);"></i> CSV
                                    </a>
                                    <a href="?pagina=exportar&inventario_id=<?= $inv['id'] ?>&formato=txt">
                                        <i class="fas fa-file-alt"   style="color:var(--gray);"></i> TXT
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($data['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($data['page'] > 1): ?>
                <a href="?pagina=inventarios_concluidos&p=<?= $data['page'] - 1 ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                <?php if ($i === $data['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php elseif (abs($i - $data['page']) <= 2): ?>
                    <a href="?pagina=inventarios_concluidos&p=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($data['page'] < $data['total_pages']): ?>
                <a href="?pagina=inventarios_concluidos&p=<?= $data['page'] + 1 ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require SRC_PATH . '/Views/layout/footer.php'; ?>
