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
// BUTTONSTATE — Sistema completo de estados de botão
// ============================================================
//
// API pública:
//   ButtonState.loading(btn, text?)   → spinner + desabilita
//   ButtonState.success(btn, text?)   → ✓ verde por 1.8s → reseta
//   ButtonState.error(btn, text?)     → ✕ vermelho por 2s → reseta
//   ButtonState.reset(btn)            → estado original
//
// Configuração via data-attributes no botão:
//   data-btn-anim="spinner|progress|pulse|dots"  (padrão: spinner)
//   data-loading-text="Salvando..."
//   data-success-text="Salvo!"
//   data-error-text="Erro!"
//   data-timeout="15000"   → ms até abortar e mostrar erro (padrão: 30s)
//
// Integração automática com formulários:
//   Qualquer <button type="submit"> dentro de <form> recebe os estados
//   automaticamente, sem nenhuma linha de JS extra nas views.
//
const ButtonState = (() => {
    const STATES   = ['loading', 'success', 'error'];
    const TIMEOUT  = 30_000; // ms padrão de timeout

    // Mapa de timers de timeout por botão (WeakMap = sem memory leak)
    const _timers = new WeakMap();

    /* ─── Estrutura interna ─────────────────────────────── */
    function _save(btn) {
        if (btn.dataset.bsOriginal) return; // já salvo
        btn.dataset.bsOriginal = btn.innerHTML;
        btn.dataset.bsDisabled = btn.disabled ? '1' : '0';
    }

    function _clearState(btn) {
        STATES.forEach(s => btn.classList.remove(`btn--${s}`));
    }

    function _scaffold(btn) {
        // Garante .btn-text e .btn-icon-state e .btn-progress-bar
        if (!btn.querySelector('.btn-text')) {
            const t = document.createElement('span');
            t.className = 'btn-text';
            t.innerHTML = btn.innerHTML;
            btn.innerHTML = '';
            btn.appendChild(t);
        }
        if (!btn.querySelector('.btn-icon-state')) {
            const ic = document.createElement('span');
            ic.className = 'btn-icon-state';
            ic.setAttribute('aria-hidden', 'true');
            btn.appendChild(ic);
        }
        if (!btn.querySelector('.btn-progress-bar')) {
            const pb = document.createElement('span');
            pb.className = 'btn-progress-bar';
            pb.setAttribute('aria-hidden', 'true');
            btn.appendChild(pb);
        }
    }

    function _setIconForAnim(btn) {
        const anim = btn.dataset.btnAnim || 'spinner';
        const ic   = btn.querySelector('.btn-icon-state');
        if (!ic) return;
        ic.innerHTML = '';

        if (anim === 'dots') {
            // 3 barras verticais animadas
            ic.innerHTML = '<i></i><i></i><i></i>';
        }
        // spinner / progress / pulse → CSS ::before cuida de tudo
    }

    /* ─── LOADING ───────────────────────────────────────── */
    function loading(btn, text = null) {
        if (!btn) return;
        _save(btn);
        _scaffold(btn);
        _clearState(btn);

        const txt = text || btn.dataset.loadingText || null;
        const textEl = btn.querySelector('.btn-text');

        if (txt && textEl) textEl.textContent = txt;

        _setIconForAnim(btn);
        btn.classList.add('btn--loading');
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.setAttribute('aria-label', txt || 'Aguarde...');

        // Timeout automático → mostra erro
        const ms = parseInt(btn.dataset.timeout || TIMEOUT, 10);
        const tid = setTimeout(() => {
            error(btn, 'Tempo esgotado');
            showToast('A operação demorou mais que o esperado. Tente novamente.', 'aviso');
        }, ms);
        _timers.set(btn, tid);
    }

    /* ─── SUCCESS ───────────────────────────────────────── */
    function success(btn, text = null) {
        if (!btn) return;
        _clearTimer(btn);
        _scaffold(btn);
        _clearState(btn);

        const txt = text || btn.dataset.successText || 'Feito!';
        const textEl = btn.querySelector('.btn-text');
        const ic     = btn.querySelector('.btn-icon-state');

        if (textEl) textEl.textContent = txt;
        if (ic) ic.innerHTML = ''; // CSS ::before renderiza ✓

        btn.classList.add('btn--success');
        btn.setAttribute('aria-label', txt);
        btn.disabled = true;

        setTimeout(() => reset(btn), 1800);
    }

    /* ─── ERROR ─────────────────────────────────────────── */
    function error(btn, text = null) {
        if (!btn) return;
        _clearTimer(btn);
        _scaffold(btn);
        _clearState(btn);

        const txt = text || btn.dataset.errorText || 'Erro!';
        const textEl = btn.querySelector('.btn-text');
        const ic     = btn.querySelector('.btn-icon-state');

        if (textEl) textEl.textContent = txt;
        if (ic) ic.innerHTML = '';

        btn.classList.add('btn--error');
        btn.setAttribute('aria-label', txt);
        btn.disabled = true;

        setTimeout(() => reset(btn), 2200);
    }

    /* ─── RESET ─────────────────────────────────────────── */
    function reset(btn) {
        if (!btn) return;
        _clearTimer(btn);
        _clearState(btn);
        btn.disabled = (btn.dataset.bsDisabled === '1');
        btn.removeAttribute('aria-busy');
        btn.removeAttribute('aria-label');
        if (btn.dataset.bsOriginal) {
            btn.innerHTML = btn.dataset.bsOriginal;
            delete btn.dataset.bsOriginal;
            delete btn.dataset.bsDisabled;
        }
    }

    function _clearTimer(btn) {
        if (_timers.has(btn)) {
            clearTimeout(_timers.get(btn));
            _timers.delete(btn);
        }
    }

    /* ─── Retrocompat com código legado ─────────────────── */
    function legacyBtnLoading(btn, start = true) {
        if (start) loading(btn);
        else       reset(btn);
    }

    return { loading, success, error, reset, _legacyToggle: legacyBtnLoading };
})();

/* ── Shim de compatibilidade: btnLoading() ainda funciona ── */
function btnLoading(btn, start = true) {
    ButtonState._legacyToggle(btn, start);
}

/* ──────────────────────────────────────────────────────────
   AUTO-BINDING DE FORMULÁRIOS
   Intercepta todo <form> com botão submit e aplica os estados
   automaticamente. Funciona com POST normal E fetch/AJAX.
   ────────────────────────────────────────────────────────── */
function _bindFormButtons() {
    document.querySelectorAll('form').forEach(form => {
        if (form.dataset.bsBound) return;
        form.dataset.bsBound = '1';

        form.addEventListener('submit', function (e) {
            const btn = form.querySelector(
                'button[type="submit"]:not([data-no-state]), ' +
                'input[type="submit"]:not([data-no-state])'
            );
            if (!btn) return;

            // Validação HTML5 inline (não bloqueia estado se inválido)
            if (!form.checkValidity()) return;

            // Inicia loading
            ButtonState.loading(btn);

            // Para formulários AJAX (fetch), o dev precisa chamar
            // ButtonState.success/error manualmente.
            // Para POST normal (page reload), o browser vai recarregar
            // e o botão volta ao estado original.
        });
    });
}

// Escuta novos forms adicionados dinamicamente (modais, etc.)
if (typeof MutationObserver !== 'undefined') {
    const _formObserver = new MutationObserver(() => _bindFormButtons());
    _onReady(() => {
        _formObserver.observe(document.body, { childList: true, subtree: true });
        _bindFormButtons();
    });
}

/* ──────────────────────────────────────────────────────────
   WRAPPER FETCH COM ESTADO AUTOMÁTICO
   Uso: fetchWithState(btn, url, options) → Promise
   ────────────────────────────────────────────────────────── */
async function fetchWithState(btn, url, options = {}) {
    ButtonState.loading(btn);

    const timeout = parseInt(btn?.dataset?.timeout || '30000', 10);
    const controller = new AbortController();
    const tid = setTimeout(() => controller.abort(), timeout);

    try {
        const res = await fetch(url, {
            ...options,
            signal: controller.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        });
        clearTimeout(tid);

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json().catch(() => ({}));

        if (data.success === false) {
            ButtonState.error(btn, btn.dataset.errorText);
            showToast(data.message || 'Erro na operação.', 'erro');
            return null;
        }

        ButtonState.success(btn, btn.dataset.successText);
        if (data.message) showToast(data.message, 'sucesso');
        return data;

    } catch (err) {
        clearTimeout(tid);
        if (err.name === 'AbortError') {
            ButtonState.error(btn, 'Tempo esgotado');
            showToast('A requisição expirou. Verifique sua conexão.', 'aviso');
        } else {
            ButtonState.error(btn, btn.dataset.errorText);
            showToast('Erro de conexão. Tente novamente.', 'erro');
        }
        return null;
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
// QR CODE SCANNER — câmera traseira forçada, sem dropdown de seleção
// ============================================================
let html5QrcodeScanner = null; // mantido para compatibilidade com exports
let _html5Qrcode = null;       // instância real (Html5Qrcode)
let qrCodeActive = false;

function iniciarScannerQR() {
    if (qrCodeActive) { fecharScannerQR(); return; }

    const modal = document.getElementById('qrScannerModal');
    if (!modal) return;

    modal.style.display = 'flex';
    qrCodeActive = true;

    _html5Qrcode = new Html5Qrcode('qr-reader');

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1,
    };

    // Tenta câmera traseira. Se não existir, cai para qualquer câmera disponível.
    _html5Qrcode.start(
        { facingMode: { exact: 'environment' } },
        config,
        onScanSuccess,
        () => {}
    ).catch(() => {
        _html5Qrcode.start(
            { facingMode: 'user' },
            config,
            onScanSuccess,
            () => {}
        ).catch((err) => {
            showToast('Não foi possível acessar a câmera.', 'erro');
            fecharScannerQR();
        });
    });
}

function onScanSuccess(text) {
    const reader = document.getElementById('qr-reader');
    if (reader) { reader.style.borderColor = 'var(--success)'; }

    if (_html5Qrcode) {
        const scanner = _html5Qrcode;
        _html5Qrcode = null; // zera antes de fechar para evitar double-stop
        scanner.stop().catch(() => {}).finally(() => { setTimeout(fecharScannerQR, 300); });
    } else {
        setTimeout(fecharScannerQR, 300);
    }

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
    if (_html5Qrcode) {
        _html5Qrcode.stop().catch(() => {}).finally(() => { _html5Qrcode.clear(); _html5Qrcode = null; });
    }
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

        fetch(`/api/ajax?acao=notificacoes&desde=${desde}`, {
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
// DOM READY — helper seguro para scripts carregados afterInteractive
// Funciona mesmo que DOMContentLoaded já tenha disparado
// ============================================================
function _onReady(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
}

_onReady(function () {

    // Vincula formulários ao sistema de estados
    _bindFormButtons();

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
window.btnLoading        = btnLoading;          // legado
window.ButtonState       = ButtonState;         // novo sistema
window.fetchWithState    = fetchWithState;      // fetch integrado
window.setupAutocomplete = setupAutocomplete;
window.fillFieldAnimated = fillFieldAnimated;
window.validateForm      = validateForm;
window.abrirPainelNotificacoes  = abrirPainelNotificacoes;
window.fecharPainelNotificacoes = fecharPainelNotificacoes;
// ============================================================
// COMPATIBILIDADE COM NEXT.JS — FUNÇÕES ADICIONAIS
// ============================================================

// Substitui a função confirmar() do PHP legado
window.confirmar = function(msg) {
    return confirm(msg);
};

// ============================================================
// AUTOCOMPLETE VIA API (usado na página de contagem)
// ============================================================
function setupAutocompleteApi(inputId, dropdownId, tipo, novoDivId = null, onSelectExtra = null) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const novoDiv  = novoDivId ? document.getElementById(novoDivId) : null;
    if (!input || !dropdown) return;

    let debounceTimer = null;

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const val = this.value.trim();
        dropdown.innerHTML = '';

        if (val.length < 2) {
            dropdown.style.display = 'none';
            if (novoDiv) novoDiv.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const resp = await fetch(`/api/ajax?tipo=${tipo}&termo=${encodeURIComponent(val)}`);
                const data = await resp.json();

                dropdown.innerHTML = '';
                if (!data || data.length === 0) {
                    dropdown.style.display = 'none';
                    if (novoDiv) novoDiv.style.display = 'block';
                    return;
                }

                data.forEach(item => {
                    const pn = item.partnumber || item.deposito || item;
                    const d  = document.createElement('div');
                    d.className = 'autocomplete-item';
                    d.innerHTML = `<strong>${_escHtml(pn)}</strong>${item.descricao ? ' — ' + _escHtml(item.descricao) : ''}`;
                    d.addEventListener('mousedown', e => {
                        e.preventDefault();
                        input.value = pn;
                        dropdown.style.display = 'none';
                        if (novoDiv) novoDiv.style.display = 'none';
                        if (onSelectExtra) onSelectExtra(item);
                        input.dispatchEvent(new Event('change'));
                    });
                    dropdown.appendChild(d);
                });

                dropdown.style.display = 'block';
                if (novoDiv) novoDiv.style.display = 'none';
            } catch(e) {
                dropdown.style.display = 'none';
            }
        }, 280);
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

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
// VERIFICAÇÃO DE STATUS DO PARTNUMBER
// ============================================================
let _verificarTimer = null;

function verificarStatusPartnumber() {
    const pn  = document.getElementById('partnumberInput')?.value.trim().toUpperCase();
    const dep = document.getElementById('depositoInput')?.value.trim().toUpperCase();
    const avisoEl    = document.getElementById('avisoStatusContagem');
    const encerradoEl = document.getElementById('erroPartNumberEncerrado');

    if (avisoEl)    avisoEl.style.display = 'none';
    if (encerradoEl) encerradoEl.style.display = 'none';

    if (!pn || !dep || pn.length < 2 || dep.length < 2) return;

    clearTimeout(_verificarTimer);
    _verificarTimer = setTimeout(async () => {
        try {
            const fd = new FormData();
            fd.append('partnumber', pn);
            fd.append('deposito', dep);
            const resp = await fetch('/api/ajax?acao=verificar_status_contagem', { method: 'POST', body: fd });
            const data = await resp.json();

            if (!data.existe) return;

            if (data.finalizado) {
                if (encerradoEl) encerradoEl.style.display = 'block';
                return;
            }

            if (!avisoEl) return;

            // let msg = '';
            // if (data.pode_nova) {
            //     if (data.numero_contagens === 1) msg = `⚡ 2ª contagem liberada pelo admin! Qtd. atual: ${data.quantidade_primaria}`;
            //     else if (data.numero_contagens === 2) msg = `⚡ 3ª contagem liberada pelo admin!`;
            // } else {
            //     if (data.numero_contagens === 1) msg = `📋 1ª contagem registrada: ${data.quantidade_primaria} — Aguardando liberação da 2ª`;
            //     else if (data.numero_contagens === 2) msg = `📋 2ª contagem: ${data.quantidade_secundaria} — Aguardando liberação da 3ª`;
            // }

            // if (msg) {
            //     avisoEl.textContent = msg;
            //     avisoEl.style.display = 'block';
            // }
        } catch(e) {}
    }, 500);
}

// Inicializa autocomplete e verificação na página de contagem
_onReady(function () {
    if (document.getElementById('depositoInput')) {
        setupAutocompleteApi('depositoInput', 'depositoDropdown', 'deposito', 'novoDepositoDiv');
    }
    if (document.getElementById('partnumberInput')) {
        setupAutocompleteApi('partnumberInput', 'pnDropdown', 'partnumber', 'novoPnDiv');
        document.getElementById('partnumberInput').addEventListener('change', verificarStatusPartnumber);
        document.getElementById('partnumberInput').addEventListener('blur', verificarStatusPartnumber);
    }
    if (document.getElementById('depositoInput')) {
        document.getElementById('depositoInput').addEventListener('change', function() {
            const pn = document.getElementById('partnumberInput')?.value.trim();
            if (pn) verificarStatusPartnumber();
        });
    }

    // Event listener para botão de notificações
    const btnNotif = document.getElementById('btnNotificacoes');
    if (btnNotif) {
        btnNotif.addEventListener('click', abrirPainelNotificacoes);
    }
    const btnFecharNotif = document.querySelector('.notif-fechar');
    if (btnFecharNotif) {
        btnFecharNotif.addEventListener('click', fecharPainelNotificacoes);
    }
});

// ============================================================
// MODAL DE AÇÕES (liberar 2ª/3ª contagem, encerrar)
// ============================================================
function abrirAcaoModal(contagemId, partnumber, deposito, inventarioId, numContagens, isAdmin) {
    const modal   = document.getElementById('acaoModal');
    const content = document.getElementById('acaoModalContent');
    if (!modal || !content) return;

    let html = `<p style="color:var(--gray);font-size:14px;margin-bottom:15px;">
        <strong>${_escHtml(partnumber)}</strong> — ${_escHtml(deposito)}
    </p>`;

    if (isAdmin) {
        if (numContagens === 1) {
            html += `<div style="display:grid;gap:10px;">
                <button class="btn btn-secondary btn-liberar" data-fase="2" data-id="${contagemId}">
                    <i class="fas fa-unlock"></i> Liberar 2ª Contagem
                </button>
                <button class="btn btn-danger btn-encerrar" data-id="${contagemId}" data-pn="${_escHtml(partnumber)}">
                    <i class="fas fa-times-circle"></i> Encerrar Contagem
                </button>
            </div>`;
        } else if (numContagens === 2) {
            html += `<div style="display:grid;gap:10px;">
                <button class="btn btn-secondary btn-liberar" data-fase="3" data-id="${contagemId}">
                    <i class="fas fa-unlock"></i> Liberar 3ª Contagem
                </button>
                <button class="btn btn-danger btn-encerrar" data-id="${contagemId}" data-pn="${_escHtml(partnumber)}">
                    <i class="fas fa-times-circle"></i> Encerrar Contagem
                </button>
            </div>`;
        } else {
            html += `<div style="display:grid;gap:10px;">
                <button class="btn btn-danger btn-encerrar" data-id="${contagemId}" data-pn="${_escHtml(partnumber)}">
                    <i class="fas fa-times-circle"></i> Encerrar Contagem
                </button>
            </div>`;
        }
    } else {
        html += `<p style="color:var(--gray)">Sem ações disponíveis.</p>`;
    }

    content.innerHTML = html;
    modal.style.display = 'flex';
    // Força reflow para ativar a transição CSS de opacity
    void modal.offsetHeight;
    modal.style.opacity = '1';

    // Bind eventos
    content.querySelectorAll('.btn-liberar').forEach(btn => {
        btn.addEventListener('click', function() {
            fecharAcaoModal();
            executarLiberar(parseInt(this.dataset.id), parseInt(this.dataset.fase));
        });
    });
    content.querySelectorAll('.btn-encerrar').forEach(btn => {
        btn.addEventListener('click', function() {
            fecharAcaoModal();
            executarEncerrar(parseInt(this.dataset.id), this.dataset.pn);
        });
    });
}

function fecharAcaoModal() {
    const modal = document.getElementById('acaoModal');
    if (!modal) return;
    modal.style.opacity = '0';
    setTimeout(() => { modal.style.display = 'none'; }, 250);
}

// ============================================================
// ATUALIZAÇÃO DINÂMICA DA LINHA APÓS LIBERAÇÃO
// ============================================================
function atualizarLinhaTabela(contagemId, dados) {
    const tr = document.querySelector(`tr[data-contagem-id="${contagemId}"]`);
    if (!tr) { setTimeout(() => location.reload(), 1500); return; }

    const numContagens = dados.numero_contagens || 1;
    const podeNova     = dados.pode_nova_contagem || false;
    const status       = dados.status || 'primaria';

    tr.className = '';
    if (dados.finalizado) {
        tr.classList.add('linha-encerrado');
    } else if (podeNova) {
        tr.classList.add('linha-primaria');
    } else {
        const classMap = {
            'primaria':   'linha-primaria',
            'secundaria': 'linha-primaria',
            'concluida':  'linha-match',
            'divergente': 'linha-divergente'
        };
        tr.classList.add(classMap[status] || 'linha-primaria');
    }

    const tdStatus = tr.querySelector('td:nth-child(7)');
    if (tdStatus) {
        let badgeHtml = '';
        if (dados.finalizado) {
            badgeHtml = '<span class="status-badge status-encerrado"><i class="fas fa-lock"></i> Encerrado</span>';
        } else if (podeNova) {
            if (numContagens === 1) badgeHtml = '<span class="status-badge status-secundaria"><i class="fas fa-unlock"></i> Aguardando 2ª contagem</span>';
            else if (numContagens === 2) badgeHtml = '<span class="status-badge status-secundaria"><i class="fas fa-unlock"></i> Aguardando 3ª contagem</span>';
            else badgeHtml = '<span class="status-badge status-primaria"><i class="fas fa-clock"></i> Em andamento</span>';
        } else {
            const badgeMap = {
                'primaria':   '<span class="status-badge status-primaria"><i class="fas fa-clock"></i> Em andamento</span>',
                'secundaria': '<span class="status-badge status-secundaria"><i class="fas fa-layer-group"></i> 2ª Contagem</span>',
                'concluida':  '<span class="status-badge status-concluida"><i class="fas fa-check-circle"></i> Concluída</span>',
                'divergente': '<span class="status-badge status-divergente"><i class="fas fa-exclamation-triangle"></i> Divergente</span>',
            };
            badgeHtml = badgeMap[status] || badgeMap.primaria;
        }
        tdStatus.innerHTML = badgeHtml;
    }

    const tdPN = tr.querySelector('td:nth-child(2)');
    if (tdPN && podeNova && !dados.finalizado) {
        const strong = tdPN.querySelector('strong');
        if (strong) {
            const oldBadge = tdPN.querySelector('.badge-liberada');
            if (oldBadge) oldBadge.remove();
            const badge = document.createElement('br');
            const small = document.createElement('small');
            small.className = 'badge-liberada';
            small.style.cssText = 'color:#1e40af;background:#dbeafe;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;display:inline-block;margin-top:3px;';
            if (numContagens === 1) small.innerHTML = '<i class="fas fa-arrow-right"></i> Liberada 2ª contagem';
            else if (numContagens === 2) small.innerHTML = '<i class="fas fa-arrow-right"></i> Liberada 3ª contagem';
            strong.parentNode.insertBefore(badge, strong.nextSibling);
            badge.parentNode.insertBefore(small, badge.nextSibling);
        }
    }

    tr.style.transition = 'background-color 0.4s ease';
    tr.style.backgroundColor = '#dbeafe';
    setTimeout(() => { tr.style.backgroundColor = ''; }, 800);
    showToast('Linha atualizada! A contagem foi liberada.', 'sucesso', 2500);
}

function executarLiberar(contagemId, fase) {
    const nomeFase = fase === 2 ? 'SEGUNDA' : 'TERCEIRA';
    showConfirm(
        `Deseja liberar a <strong>${nomeFase} contagem</strong> para este item?<br>
         <small style="color:var(--gray)">O operador poderá registrar a próxima contagem.</small>`,
        () => {
            const acao = fase === 2 ? 'liberar_segunda' : 'liberar_terceira';
            const fd   = new FormData();
            if (window.csrfToken) fd.append('csrf_token', window.csrfToken);
            fd.append('contagem_id', contagemId);

            const btn = document.querySelector(`tr[data-contagem-id="${contagemId}"] .btn-acao`);
            if (btn) btnLoading(btn, true);

            fetch(`/api/ajax?acao=${acao}`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (btn) btnLoading(btn, false);
                    if (d.success) {
                        showToast(d.message, 'sucesso');
                        if (d.data && d.data.id) {
                            setTimeout(() => atualizarLinhaTabela(d.data.id, d.data), 400);
                        } else {
                            setTimeout(() => location.reload(), 1500);
                        }
                    } else {
                        showToast(d.message || 'Erro ao liberar contagem.', 'erro');
                    }
                })
                .catch(err => {
                    if (btn) btnLoading(btn, false);
                    showToast('Erro de comunicação com o servidor.', 'erro');
                });
        }
    );
}

function executarEncerrar(contagemId, partnumber) {
    showConfirm(
        `Encerrar a contagem de <strong>${partnumber}</strong>?<br>
         <small style="color:var(--gray)">Esta ação não pode ser desfeita. Nenhuma nova contagem será aceita para este item.</small>`,
        () => {
            const fd = new FormData();
            if (window.csrfToken) fd.append('csrf_token', window.csrfToken);
            fd.append('contagem_id', contagemId);

            const btn = document.querySelector(`tr[data-contagem-id="${contagemId}"] .btn-acao`);
            if (btn) btnLoading(btn, true);

            fetch('/api/ajax?acao=finalizar_contagem', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (btn) btnLoading(btn, false);
                    if (d.success) {
                        showToast(d.message, 'sucesso');
                        const tr = document.querySelector(`tr[data-contagem-id="${contagemId}"]`);
                        if (tr) {
                            tr.className = 'linha-encerrado';
                            const tdStatus = tr.querySelector('td:nth-child(7)');
                            if (tdStatus) tdStatus.innerHTML = '<span class="status-badge status-encerrado"><i class="fas fa-lock"></i> Encerrado</span>';
                            const tdAcoes = tr.querySelector('td:nth-child(10)');
                            if (tdAcoes) tdAcoes.innerHTML = '<span style="color:var(--gray);font-size:12px;"><i class="fas fa-lock"></i></span>';
                            tr.style.transition = 'all 0.4s ease';
                            tr.style.backgroundColor = '#fef2f2';
                            setTimeout(() => { tr.style.backgroundColor = ''; }, 800);
                        }
                    } else {
                        showToast(d.message || 'Erro ao encerrar.', 'erro');
                    }
                })
                .catch(() => {
                    if (btn) btnLoading(btn, false);
                    showToast('Erro de comunicação com o servidor.', 'erro');
                });
        }
    );
}

window.abrirAcaoModal     = abrirAcaoModal;
window.fecharAcaoModal    = fecharAcaoModal;
window.executarLiberar    = executarLiberar;
window.executarEncerrar   = executarEncerrar;
window.atualizarLinhaTabela = atualizarLinhaTabela;
window.verificarStatusPartnumber = verificarStatusPartnumber;
window.iniciarScannerQR   = iniciarScannerQR;
window.fecharScannerQR    = fecharScannerQR;