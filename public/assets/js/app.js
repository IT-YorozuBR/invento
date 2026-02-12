/* =============================================
   SISTEMA DE INVENTÁRIO - JAVASCRIPT PRINCIPAL
   ============================================= */

// ---- QR Code Scanner ----
let html5QrcodeScanner = null;
let qrCodeActive = false;

function iniciarScannerQR() {
    if (qrCodeActive) {
        fecharScannerQR();
        return;
    }

    const readerElement = document.getElementById('qr-reader');
    if (!readerElement) {
        console.error('Elemento qr-reader não encontrado');
        return;
    }

    // Mostrar modal
    document.getElementById('qrScannerModal').style.display = 'flex';
    qrCodeActive = true;

    // Configurar scanner
    html5QrcodeScanner = new Html5QrcodeScanner(
        "qr-reader",
        {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]
        },
        false
    );

    html5QrcodeScanner.render(onScanSuccess, onScanError);
}

function onScanSuccess(decodedText, decodedResult) {
    console.log(`QR Code lido: ${decodedText}`);

    html5QrcodeScanner.clear();
    fecharScannerQR();

    if (decodedText.length >= 4) {
        const deposito = decodedText.substring(0, 3).toUpperCase();
        const partnumber = decodedText.substring(3);

        // Preencher campo de depósito (input)
        const depositoInput = document.getElementById('depositoInput');
        if (depositoInput) {
            depositoInput.value = deposito;

            // Disparar evento 'input' para atualizar autocomplete e campo de novo depósito
            const evt = new Event('input', { bubbles: true });
            depositoInput.dispatchEvent(evt);
        }

        // Preencher campo de part number
        const partnumberInput = document.getElementById('partnumberInput');
        if (partnumberInput) {
            partnumberInput.value = partnumber;
            partnumberInput.focus();
            const evt = new Event('input', { bubbles: true });
            partnumberInput.dispatchEvent(evt);
        }

        // Foco na quantidade
        setTimeout(() => {
            const quantidadeInput = document.getElementById('quantidade');
            if (quantidadeInput) quantidadeInput.focus();
        }, 100);

        showToast(`QR Code lido!\nDepósito: ${deposito}\nPart Number: ${partnumber}`, 'sucesso');
    } else {
        showToast('Formato inválido. Esperado: 3 letras (depósito) + partnumber', 'erro');
    }
}

function onScanError(errorMessage) {
    // Não mostrar erros contínuos de scan
    // console.log(`Erro scan: ${errorMessage}`);
}

function fecharScannerQR() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(err => console.error('Erro ao limpar scanner:', err));
        html5QrcodeScanner = null;
    }
    const modal = document.getElementById('qrScannerModal');
    if (modal) modal.style.display = 'none';
    qrCodeActive = false;
}

document.addEventListener('DOMContentLoaded', function () {

    // ---- Validação de formulários ----
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function (e) {
            let valid = true;
            form.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    valid = false;
                    input.style.borderColor = 'var(--danger)';
                    if (valid === false) input.focus(); // foco no primeiro inválido
                } else {
                    input.style.borderColor = '';
                }
            });
            if (!valid) {
                e.preventDefault();
                showToast('Preencha todos os campos obrigatórios!', 'erro');
            }
        });
    });

    // ---- Auto-foco no primeiro campo ----
    const firstInput = document.querySelector('form input:not([type=hidden]):not([readonly])');
    if (firstInput) firstInput.focus();

    // ---- Dropdowns ----
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const menu = this.nextElementSibling;
            const isOpen = menu.style.display === 'block';
            // Fechar todos
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
            menu.style.display = isOpen ? 'none' : 'block';
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
    });

    // ---- Flash messages: auto-fade ----
    document.querySelectorAll('.mensagem[data-auto-hide]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });

    // ---- Alerta de saída com dados não salvos ----
    const contagemForm = document.getElementById('formContagem');
    if (contagemForm) {
        let dirty = false;
        contagemForm.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('change', () => { dirty = true; });
        });
        contagemForm.addEventListener('submit', () => { dirty = false; });
        window.addEventListener('beforeunload', e => {
            if (dirty) {
                e.preventDefault();
                return (e.returnValue = 'Você tem dados não salvos. Deseja sair?');
            }
        });
    }

    // ---- Modal Terceira Contagem ----
    const overlay = document.getElementById('modalTerceiraContagem');
    if (overlay) {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) fecharModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fecharModal();
        });
    }
});

// ---- Autocomplete ----
let debounceTimers = {};

function autocomplete(inputId, dropdownId, type) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    if (!input || !dropdown) return;

    input.addEventListener('input', function () {
        clearTimeout(debounceTimers[inputId]);
        const term = this.value.trim();
        if (term.length < 2) {
            dropdown.style.display = 'none';
            return;
        }
        debounceTimers[inputId] = setTimeout(() => {
            fetch(`?pagina=ajax&tipo=${type}&termo=${encodeURIComponent(term)}`)
                .then(r => r.json())
                .then(items => {
                    dropdown.innerHTML = '';
                    if (!items.length) { dropdown.style.display = 'none'; return; }
                    items.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        const label = type === 'partnumber' ? item.partnumber : item.deposito;
                        const sub = type === 'partnumber' ? item.descricao : item.localizacao;
                        div.innerHTML = `<strong>${escHtml(label)}</strong>${sub ? `<small>${escHtml(sub)}</small>` : ''}`;
                        div.addEventListener('click', () => {
                            input.value = label;
                            dropdown.style.display = 'none';
                            input.dispatchEvent(new Event('change'));
                        });
                        dropdown.appendChild(div);
                    });
                    dropdown.style.display = 'block';
                })
                .catch(() => { dropdown.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

// ---- Modal Terceira Contagem ----
function abrirModalTerceiraContagem(contagemId, primaria, secundaria) {
    const overlay = document.getElementById('modalTerceiraContagem');
    if (!overlay) return;
    document.getElementById('contagemId').value = contagemId;
    document.getElementById('primeiraContagemValor').textContent = primaria;
    document.getElementById('segundaContagemValor').textContent = secundaria;
    document.getElementById('quantidadeTerceira').value = Math.round((primaria + secundaria) / 2);
    overlay.style.display = 'flex';
    document.getElementById('quantidadeTerceira').focus();
}

function fecharModal() {
    const overlay = document.getElementById('modalTerceiraContagem');
    if (overlay) overlay.style.display = 'none';
}

// ---- Toast ----
function showToast(text, type = 'sucesso') {
    const toast = document.createElement('div');
    toast.className = `mensagem ${type}`;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:320px;box-shadow:0 4px 12px rgba(0,0,0,.15);';
    toast.textContent = text;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity .4s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

// ---- Utilitários ----
function escHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function confirmar(msg) {
    return confirm(msg);
}
