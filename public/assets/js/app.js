/* =============================================
   SISTEMA DE INVENTÁRIO — JS v2
   ============================================= */

const CONFIG = {
    DEBOUNCE_DELAY:   280,
    TOAST_DURATION:   4000,
    ANIMATION_MS:     300,
    NOTIF_POLL_MS:    45000, // 45s — leve, sem sobrecarregar
};

// ============================================================
// TOAST — substitui alert() e flash msg
// ============================================================
const toastQueue = [];
let toastActive = false;

function showToast(text, type = 'info', duration = CONFIG.TOAST_DURATION) {
    const icons = { sucesso: '✓', erro: '✕', aviso: '⚠', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    // Calcula posição vertical empilhada
    const offset = (document.querySelectorAll('.toast').length) * 72;
    toast.style.top = (20 + offset) + 'px';

    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span style="flex:1;line-height:1.4">${text}</span>
        <span class="toast-close">×</span>
    `;

    document.body.appendChild(toast);

    const remove = () => {
        if (!toast.isConnected) return;
        toast.classList.add('saindo');
        setTimeout(() => toast.remove(), CONFIG.ANIMATION_MS);
    };

    toast.addEventListener('click', remove);
    setTimeout(remove, duration);
    return toast;
}

// Substitui alert() nativo por toast
window._origAlert = window.alert;
window.alert = function(msg) {
    const isSuccess = msg.startsWith('✅') || msg.startsWith('🔒');
    const isError   = msg.startsWith('❌') || msg.startsWith('⚠');
    const type      = isSuccess ? 'sucesso' : isError ? 'erro' : 'info';
    showToast(msg, type);
};

// ============================================================
// BUTTON LOADING
// ============================================================
function btnLoading(btn, start = true) {
    if (start) {
        if (!btn.dataset.origHtml) btn.dataset.origHtml = btn.innerHTML;
        btn.classList.add('loading');
        btn.disabled = true;
        if (btn.querySelector('.btn-text')) {
            btn.querySelector('.btn-text').style.opacity = '0';
        } else {
            btn.innerHTML = `<span class="btn-text" style="opacity:0">${btn.dataset.origHtml}</span>`;
        }
    } else {
        btn.classList.remove('loading');
        btn.disabled = false;
        if (btn.dataset.origHtml) btn.innerHTML = btn.dataset.origHtml;
    }
}

// ============================================================
// MODAL DE CONFIRMAÇÃO (substitui confirm())
// ============================================================
function showConfirm(msg, onYes, onNo = null) {
    // Remove modal existente se houver
    document.getElementById('_confirmdlg')?.remove();

    const overlay = document.createElement('div');
    overlay.id = '_confirmdlg';
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-title"><i class="fas fa-question-circle" style="color:var(--warning)"></i> Confirmação</div>
            <p style="color:#475569;margin-bottom:20px;line-height:1.5">${msg}</p>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button id="_confirmNo"  class="btn btn-ghost">Cancelar</button>
                <button id="_confirmYes" class="btn btn-primary"><span class="btn-text">Confirmar</span></button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    const close = () => {
        overlay.classList.add('fade-out');
        setTimeout(() => overlay.remove(), CONFIG.ANIMATION_MS);
    };

    overlay.querySelector('#_confirmYes').addEventListener('click', () => { close(); onYes(); });
    overlay.querySelector('#_confirmNo').addEventListener('click',  () => { close(); if (onNo) onNo(); });
    overlay.addEventListener('click', e => { if (e.target === overlay) { close(); if (onNo) onNo(); } });
    document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
    });
}

// ============================================================
// QR CODE SCANNER
// ============================================================
let html5QrcodeScanner = null;
let qrCodeActive = false;

function iniciarScannerQR() {
    if (qrCodeActive) { fecharScannerQR(); return; }

    const modal = document.getElementById('qrScannerModal');
    if (!modal) return;

    modal.style.display = 'flex';
    qrCodeActive = true;

    html5QrcodeScanner = new Html5QrcodeScanner(
        'qr-reader',
        { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
        false
    );
    html5QrcodeScanner.render(onScanSuccess, () => {});
}

function onScanSuccess(text) {
    const reader = document.getElementById('qr-reader');
    if (reader) { reader.style.borderColor = 'var(--success)'; }

    html5QrcodeScanner.clear();
    setTimeout(fecharScannerQR, 300);

    if (text.length >= 4) {
        const dep = text.substring(0, 3).toUpperCase();
        const pn  = text.substring(3);
        fillFieldAnimated('depositoInput', dep);
        fillFieldAnimated('partnumberInput', pn);
        setTimeout(() => {
            const q = document.getElementById('quantidade');
            if (q) { q.focus(); q.classList.add('pulse'); setTimeout(() => q.classList.remove('pulse'), 800); }
        }, 150);
        showToast(`QR lido! Depósito: ${dep} | PN: ${pn}`, 'sucesso');
    } else {
        showToast('Formato QR inválido. Esperado: 3 letras + partnumber', 'erro');
    }
}

function fecharScannerQR() {
    html5QrcodeScanner?.clear().catch(() => {});
    html5QrcodeScanner = null;
    const modal = document.getElementById('qrScannerModal');
    if (modal) { modal.classList.add('fade-out'); setTimeout(() => { modal.style.display = 'none'; modal.classList.remove('fade-out'); }, CONFIG.ANIMATION_MS); }
    qrCodeActive = false;
}

function fillFieldAnimated(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value;
    el.classList.add('success');
    el.dispatchEvent(new Event('input', { bubbles: true }));
    setTimeout(() => el.classList.remove('success'), 900);
}

// ============================================================
// AUTOCOMPLETE LEVE (usa arrays locais já injetados pelo PHP)
// ============================================================
function setupAutocomplete(inputId, dropdownId, dataList, novoDivId = null) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const novoDiv  = novoDivId ? document.getElementById(novoDivId) : null;
    if (!input || !dropdown) return;

    input.addEventListener('input', function () {
        const val = this.value.trim().toLowerCase();
        dropdown.innerHTML = '';

        if (!val) { dropdown.style.display = 'none'; if (novoDiv) novoDiv.style.display = 'none'; return; }

        const matches = dataList.filter(i => i.toLowerCase().includes(val)).slice(0, 10);

        matches.forEach(item => {
            const d = document.createElement('div');
            d.className = 'autocomplete-item';
            d.textContent = item;
            d.addEventListener('mousedown', e => {
                e.preventDefault();
                input.value = item;
                dropdown.style.display = 'none';
                if (novoDiv) novoDiv.style.display = 'none';
                input.dispatchEvent(new Event('blur'));
            });
            dropdown.appendChild(d);
        });

        dropdown.style.display = matches.length ? 'block' : 'none';
        if (novoDiv) novoDiv.style.display = dataList.some(i => i.toLowerCase() === val) ? 'none' : 'block';
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
    });

    // Navegação por teclado
    input.addEventListener('keydown', e => {
        const items = dropdown.querySelectorAll('.autocomplete-item');
        if (!items.length) return;
        let idx = Array.from(items).findIndex(i => i.classList.contains('selected'));
        if (e.key === 'ArrowDown')  { e.preventDefault(); idx = (idx + 1) % items.length; }
        else if (e.key === 'ArrowUp') { e.preventDefault(); idx = idx <= 0 ? items.length - 1 : idx - 1; }
        else if (e.key === 'Enter' && idx >= 0) { e.preventDefault(); items[idx].dispatchEvent(new MouseEvent('mousedown')); return; }
        else return;
        items.forEach(i => i.classList.remove('selected'));
        items[idx]?.classList.add('selected');
        items[idx]?.scrollIntoView({ block: 'nearest' });
    });
}

// ============================================================
// POLLING DE NOTIFICAÇÕES (admin only, leve)
// ============================================================
const NOTIF_KEY = 'notif_last_ts'; // sessionStorage key

function iniciarPollingNotificacoes() {
    if (!document.getElementById('btnNotificacoes')) return; // não é admin

    // Inicializa timestamp: usa o armazenado ou 60s atrás
    if (!sessionStorage.getItem(NOTIF_KEY)) {
        sessionStorage.setItem(NOTIF_KEY, Math.floor(Date.now() / 1000) - 60);
    }

    function poll() {
        const desde = sessionStorage.getItem(NOTIF_KEY) || (Math.floor(Date.now() / 1000) - 60);

        fetch(`?pagina=ajax&acao=notificacoes&desde=${desde}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data || data.total === 0) return;

            // Atualiza badge
            const badge = document.getElementById('notifBadge');
            const bell  = document.getElementById('btnNotificacoes');
            if (!badge || !bell) return;

            const prevCount = parseInt(badge.dataset.count || '0');
            const newCount  = prevCount + data.total;

            badge.dataset.count = newCount;
            badge.textContent   = newCount > 99 ? '99+' : newCount;
            badge.style.display = 'flex';
            bell.classList.add('has-new');

            // Re-anima sino
            bell.querySelector('i').style.animation = 'none';
            setTimeout(() => {
                bell.querySelector('i').style.animation = '';
                bell.querySelector('i').style.animation = 'bellShake 1s ease 0s 2';
            }, 10);

            // Popula lista
            const lista = document.getElementById('notifLista');
            if (!lista) return;

            const vazio = lista.querySelector('.notif-vazio');
            if (vazio) vazio.remove();

            data.items.forEach(item => {
                const faseLabel = ['', '1ª', '2ª', '3ª'][item.fase] || item.fase + 'ª';
                const ago = _timeAgo(item.ts);
                const el = document.createElement('div');
                el.className = 'notif-item';
                el.innerHTML = `
                    <div class="notif-item-usuario"><i class="fas fa-user-clock"></i> ${_escHtml(item.usuario_nome)}</div>
                    <div class="notif-item-detalhe">${faseLabel} contagem · <strong>${_escHtml(item.partnumber)}</strong> · ${_escHtml(item.deposito)}</div>
                    <div class="notif-item-time">${ago}</div>
                `;
                lista.prepend(el);
            });

            // Mantém máximo de 20 itens na lista
            const all = lista.querySelectorAll('.notif-item');
            if (all.length > 20) all[all.length - 1].remove();

            // Atualiza timestamp para a próxima poll
            sessionStorage.setItem(NOTIF_KEY, Math.floor(Date.now() / 1000));
        })
        .catch(() => {}); // silencia erros de rede — não é crítico
    }

    // Primeira poll após 3s (aguarda página carregar)
    setTimeout(poll, 3000);
    setInterval(poll, CONFIG.NOTIF_POLL_MS);
}

function abrirPainelNotificacoes() {
    const painel = document.getElementById('painelNotificacoes');
    const badge  = document.getElementById('notifBadge');
    const bell   = document.getElementById('btnNotificacoes');
    if (!painel) return;

    const aberto = painel.style.display !== 'none';
    painel.style.display = aberto ? 'none' : 'block';

    if (!aberto) {
        // Zera badge ao abrir
        if (badge) { badge.style.display = 'none'; badge.dataset.count = '0'; }
        if (bell)  bell.classList.remove('has-new');
        // Avança timestamp para não repetir as mesmas notificações
        sessionStorage.setItem(NOTIF_KEY, Math.floor(Date.now() / 1000));
    }
}

function fecharPainelNotificacoes() {
    const painel = document.getElementById('painelNotificacoes');
    if (painel) painel.style.display = 'none';
}

// Fecha painel ao clicar fora
document.addEventListener('click', e => {
    const painel = document.getElementById('painelNotificacoes');
    const bell   = document.getElementById('btnNotificacoes');
    if (painel && painel.style.display !== 'none' && !painel.contains(e.target) && !bell?.contains(e.target)) {
        painel.style.display = 'none';
    }
});

function _timeAgo(ts) {
    const diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 60)   return 'agora mesmo';
    if (diff < 3600) return Math.floor(diff / 60) + ' min atrás';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
    return Math.floor(diff / 86400) + 'd atrás';
}

function _escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ============================================================
// VALIDAÇÃO DE FORMULÁRIO
// ============================================================
function validateForm(form) {
    let ok = true;
    form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
            ok = false;
            field.classList.add('error');
            const lbl = form.querySelector(`label[for="${field.id}"]`);
            if (lbl) lbl.style.color = 'var(--danger)';
            setTimeout(() => {
                field.classList.remove('error');
                if (lbl) lbl.style.color = '';
            }, 1200);
        }
    });
    if (!ok) showToast('Preencha todos os campos obrigatórios!', 'aviso');
    return ok;
}

// ============================================================
// DOM READY
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // Auto-hide flash messages do servidor
    document.querySelectorAll('.mensagem[data-auto-hide]').forEach(el => {
        setTimeout(() => {
            el.classList.add('fade-out');
            setTimeout(() => el.remove(), CONFIG.ANIMATION_MS);
        }, 4500);
    });

    // Dropdowns
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', e => {
            e.stopPropagation();
            const menu = toggle.nextElementSibling;
            const open = menu?.style.display === 'block';
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
            if (!open && menu) menu.style.display = 'block';
        });
    });
    document.addEventListener('click', () =>
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none')
    );

    // Botões com data-confirm usam showConfirm
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const msg = btn.dataset.confirm;
            const href = btn.dataset.href || btn.href;
            showConfirm(msg, () => { if (href) location.href = href; });
        });
    });

    // Inicia polling de notificações para admin
    iniciarPollingNotificacoes();
});

// ============================================================
// MODAL TERCEIRA CONTAGEM (legado — mantido para compatibilidade)
// ============================================================
function abrirModalTerceiraContagem(contagemId, primaria, secundaria) {
    const overlay = document.getElementById('modalTerceiraContagem');
    if (!overlay) return;
    document.getElementById('contagemId').value = contagemId;
    document.getElementById('primeiraContagemValor').textContent = primaria;
    document.getElementById('segundaContagemValor').textContent  = secundaria;
    const input = document.getElementById('quantidadeTerceira');
    if (input) input.value = Math.round((primaria + secundaria) / 2);
    overlay.style.display = 'flex';
    overlay.classList.add('fade-in');
    setTimeout(() => { input?.focus(); input?.select(); }, 300);
}

function fecharModal() {
    const overlay = document.getElementById('modalTerceiraContagem');
    if (!overlay) return;
    overlay.classList.add('fade-out');
    setTimeout(() => { overlay.style.display = 'none'; overlay.classList.remove('fade-out', 'fade-in'); }, CONFIG.ANIMATION_MS);
}

// ============================================================
// EXPORTS
// ============================================================
window.showToast         = showToast;
window.showConfirm       = showConfirm;
window.btnLoading        = btnLoading;
window.setupAutocomplete = setupAutocomplete;
window.fillFieldAnimated = fillFieldAnimated;
window.validateForm      = validateForm;
window.abrirPainelNotificacoes  = abrirPainelNotificacoes;
window.fecharPainelNotificacoes = fecharPainelNotificacoes;