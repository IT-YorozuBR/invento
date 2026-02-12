<?php use App\Core\Security; ?>
<?php if (($_GET['pagina'] ?? 'login') !== 'login' && Security::isAuthenticated()): ?>
<footer class="footer">
    <p>&copy; <?= date('Y') ?> Sistema de Inventário </p>
    <p style="font-size:12px;opacity:.6;margin-top:4px;">Estrutura MVC &middot; PHP 8+ &middot; MySQL</p>
</footer>
<?php endif; ?>

<!-- Modal Terceira Contagem -->
<div id="modalTerceiraContagem" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-users"></i> Registrar Terceira Contagem</h3>
        </div>
        <div class="modal-content">
            <p style="color:var(--gray);margin-bottom:15px;">
                Divergência significativa entre 1ª e 2ª contagens. Registre uma terceira para resolver.
            </p>
            <div style="background:#f8f9fa;padding:15px;border-radius:var(--border-r);margin-bottom:20px;display:grid;gap:8px;">
                <div style="display:flex;justify-content:space-between;">
                    <span>1ª Contagem:</span>
                    <strong id="primeiraContagemValor" style="color:var(--secondary);"></strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <strong>2ª Contagem:</strong>
                    <strong id="segundaContagemValor" style="color:var(--warning);"></strong>
                </div>
            </div>
            <form id="formTerceiraContagem" method="POST" action="?pagina=contagem">
                <input type="hidden" name="csrf_token" value="<?= \App\Core\Security::generateCsrfToken() ?>">
                <input type="hidden" name="acao_contagem" value="segunda_contagem">
                <input type="hidden" id="contagemId" name="contagem_id">
                <div class="form-group">
                    <label for="quantidadeTerceira"><i class="fas fa-box"></i> Quantidade (3ª Contagem):</label>
                    <input type="number" id="quantidadeTerceira" name="quantidade_secundaria"
                           required min="0" step="0.0001" placeholder="Digite a quantidade">
                </div>
            </form>
        </div>
        <div class="modal-actions">
            <button onclick="fecharModal()" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button onclick="document.getElementById('formTerceiraContagem').submit()" class="btn btn-primary">
                <i class="fas fa-check"></i> Registrar 3ª Contagem
            </button>
        </div>
    </div>
</div>

<!-- Modal QR Code Scanner -->
<div id="qrScannerModal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-qrcode"></i> Scanner de QR Code</h3>
        </div>
        <div class="modal-content">
            <p style="color:var(--gray);margin-bottom:15px;text-align:center;">
                Posicione o QR Code dentro da área marcada
            </p>
            <div id="qr-reader" style="width:100%;"></div>
            <div style="margin-top:15px;padding:12px;background:#f0f8ff;border-radius:var(--border-r);font-size:13px;color:var(--secondary);">
                <strong><i class="fas fa-info-circle"></i> Formato esperado:</strong><br>
                <code style="background:white;padding:4px 8px;border-radius:4px;margin-top:6px;display:inline-block;">DEP + PARTNUMBER</code>
                <br><small style="color:var(--gray);margin-top:4px;display:block;">Exemplo: B9M555119496R → Depósito: <strong>B9M</strong> | Part Number: <strong>555119496R</strong></small>
            </div>
        </div>
        <div class="modal-actions">
            <button onclick="fecharScannerQR()" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

</main>
<script src="assets/js/app.js"></script>
</body>
</html>
