/* =============================================
   SISTEMA DE INVENTÁRIO - JAVASCRIPT APRIMORADO
   Melhorias de UX com Loading States e Feedback
   ============================================= */

// ---- Configurações Globais ----
const CONFIG = {
    DEBOUNCE_DELAY: 300,
    TOAST_DURATION: 3500,
    ANIMATION_DURATION: 400,
    LOADING_MIN_TIME: 500 // Tempo mínimo para exibir loading (evita flash)
};

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

    // Mostrar modal com animação
    const modal = document.getElementById('qrScannerModal');
    modal.style.display = 'flex';
    modal.classList.add('fade-in');
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

    // Feedback visual de sucesso
    const readerElement = document.getElementById('qr-reader');
    if (readerElement) {
        readerElement.style.borderColor = 'var(--success)';
        readerElement.style.boxShadow = '0 0 20px rgba(39, 174, 96, 0.5)';
    }

    html5QrcodeScanner.clear();
    
    setTimeout(() => {
        fecharScannerQR();
    }, 300);

    if (decodedText.length >= 4) {
        const deposito = decodedText.substring(0, 3).toUpperCase();
        const partnumber = decodedText.substring(3);

        // Preencher campos com animação
        fillFieldWithAnimation('depositoInput', deposito);
        fillFieldWithAnimation('partnumberInput', partnumber);

        // Foco na quantidade com delay
        setTimeout(() => {
            const quantidadeInput = document.getElementById('quantidade');
            if (quantidadeInput) {
                quantidadeInput.focus();
                quantidadeInput.classList.add('pulse');
                setTimeout(() => quantidadeInput.classList.remove('pulse'), 1000);
            }
        }, 100);

        showToast(`✓ QR Code lido com sucesso!\nDepósito: ${deposito}\nPart Number: ${partnumber}`, 'sucesso');
    } else {
        showToast('⚠ Formato inválido. Esperado: 3 letras (depósito) + partnumber', 'erro');
    }
}

function onScanError(errorMessage) {
    // Não mostrar erros contínuos de scan
}

function fecharScannerQR() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(err => console.error('Erro ao limpar scanner:', err));
        html5QrcodeScanner = null;
    }
    
    const modal = document.getElementById('qrScannerModal');
    if (modal) {
        modal.classList.add('fade-out');
        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.remove('fade-out', 'fade-in');
        }, CONFIG.ANIMATION_DURATION);
    }
    
    qrCodeActive = false;
}

// ---- Utilitário para preenchimento animado de campos ----
function fillFieldWithAnimation(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    // Adiciona classe de animação
    field.classList.add('success');
    field.value = value;

    // Disparar evento 'input' para atualizar autocomplete
    const evt = new Event('input', { bubbles: true });
    field.dispatchEvent(evt);

    // Remover classe após animação
    setTimeout(() => {
        field.classList.remove('success');
    }, 1000);
}

// ---- Gerenciador de Loading States em Botões ----
class ButtonLoadingManager {
    constructor(button) {
        this.button = button;
        this.originalContent = button.innerHTML;
        this.startTime = null;
    }

    start() {
        this.startTime = Date.now();
        this.button.classList.add('loading');
        this.button.disabled = true;
        
        // Salvar conteúdo original
        this.button.setAttribute('data-original-content', this.originalContent);
    }

    async end(minDelay = CONFIG.LOADING_MIN_TIME) {
        const elapsed = Date.now() - this.startTime;
        const remainingTime = Math.max(0, minDelay - elapsed);

        // Aguarda tempo mínimo se necessário
        if (remainingTime > 0) {
            await new Promise(resolve => setTimeout(resolve, remainingTime));
        }

        this.button.classList.remove('loading');
        this.button.disabled = false;
    }

    error() {
        this.button.classList.remove('loading');
        this.button.disabled = false;
        this.button.classList.add('error');
        
        setTimeout(() => {
            this.button.classList.remove('error');
        }, 300);
    }
}

// ---- Interceptor de formulários com loading ----
function setupFormWithLoading(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!submitButton) return;

        const loadingManager = new ButtonLoadingManager(submitButton);
        loadingManager.start();

        // Se a validação falhar, remover loading
        const isValid = validateForm(form);
        if (!isValid) {
            e.preventDefault();
            loadingManager.error();
            return;
        }
    });
}

// ---- Validação de Formulários Aprimorada ----
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            
            // Adiciona classe de erro com animação
            field.classList.add('error');
            
            // Feedback visual no label
            const label = form.querySelector(`label[for="${field.id}"]`);
            if (label) {
                label.style.color = 'var(--danger)';
            }
            
            // Remove classe após animação
            setTimeout(() => {
                field.classList.remove('error');
                if (label) {
                    label.style.color = '';
                }
            }, 1000);
            
            // Foco no primeiro campo inválido
            if (isValid === false) {
                field.focus();
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
    
    if (!isValid) {
        showToast('⚠ Preencha todos os campos obrigatórios!', 'erro');
    }
    
    return isValid;
}

// ---- Inicialização do DOM ----
document.addEventListener('DOMContentLoaded', function () {

    // ---- Configurar formulários com loading states ----
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        setupFormWithLoading(form.id);
    });

    // ---- Auto-foco no primeiro campo com animação ----
    const firstInput = document.querySelector('form input:not([type=hidden]):not([readonly])');
    if (firstInput) {
        firstInput.focus();
        firstInput.classList.add('pulse');
        setTimeout(() => firstInput.classList.remove('pulse'), 1000);
    }

    // ---- Dropdowns com animações ----
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const menu = this.nextElementSibling;
            const isOpen = menu.style.display === 'block';
            
            // Fechar todos os dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                m.style.display = 'none';
                m.classList.remove('slide-down');
            });
            
            // Toggle do dropdown atual
            if (!isOpen) {
                menu.style.display = 'block';
                menu.classList.add('slide-down');
            }
        });
    });

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu').forEach(m => {
            m.style.display = 'none';
            m.classList.remove('slide-down');
        });
    });

    // ---- Flash messages com animação de fade ----
    document.querySelectorAll('.mensagem[data-auto-hide]').forEach(el => {
        setTimeout(() => {
            el.classList.add('fade-out');
            setTimeout(() => el.remove(), CONFIG.ANIMATION_DURATION);
        }, 4000);
    });

    // ---- Alerta de saída com dados não salvos ----
    const contagemForm = document.getElementById('formContagem');
    if (contagemForm) {
        let isDirty = false;
        
        contagemForm.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('change', () => { isDirty = true; });
        });
        
        contagemForm.addEventListener('submit', () => { isDirty = false; });
        
        window.addEventListener('beforeunload', e => {
            if (isDirty) {
                e.preventDefault();
                return (e.returnValue = 'Você tem dados não salvos. Deseja sair?');
            }
        });
    }

    // ---- Modal Terceira Contagem com animações ----
    const overlay = document.getElementById('modalTerceiraContagem');
    if (overlay) {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) fecharModal();
        });
        
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fecharModal();
        });
    }

    // ---- Adicionar ripple effect em todos os botões ----
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // ---- Observador de elementos para animações ao scroll ----
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observar cards e elementos de tabela
    document.querySelectorAll('.card, .table-container').forEach(el => {
        observer.observe(el);
    });
});

// ---- Autocomplete Aprimorado ----
let debounceTimers = {};
let activeRequests = {};

function autocomplete(inputId, dropdownId, type) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    if (!input || !dropdown) return;

    // Adicionar indicador de loading no input
    const loadingIndicator = createLoadingIndicator();
    input.parentElement.style.position = 'relative';

    input.addEventListener('input', function () {
        clearTimeout(debounceTimers[inputId]);
        
        // Cancelar requisição anterior se existir
        if (activeRequests[inputId]) {
            activeRequests[inputId].abort();
        }

        const term = this.value.trim();
        
        if (term.length < 2) {
            dropdown.style.display = 'none';
            removeLoadingIndicator(loadingIndicator);
            return;
        }

        // Mostrar loading
        showLoadingIndicator(input, loadingIndicator);

        debounceTimers[inputId] = setTimeout(() => {
            // Criar AbortController para esta requisição
            const controller = new AbortController();
            activeRequests[inputId] = controller;

            fetch(`?pagina=ajax&tipo=${type}&termo=${encodeURIComponent(term)}`, {
                signal: controller.signal
            })
                .then(r => r.json())
                .then(items => {
                    removeLoadingIndicator(loadingIndicator);
                    dropdown.innerHTML = '';
                    
                    if (!items.length) {
                        dropdown.style.display = 'none';
                        return;
                    }

                    items.forEach((item, index) => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.style.animationDelay = `${index * 0.05}s`;
                        
                        const label = type === 'partnumber' ? item.partnumber : item.deposito;
                        const sub = type === 'partnumber' ? item.descricao : item.localizacao;
                        
                        div.innerHTML = `<strong>${escHtml(label)}</strong>${sub ? `<small>${escHtml(sub)}</small>` : ''}`;
                        
                        div.addEventListener('click', () => {
                            input.value = label;
                            dropdown.style.display = 'none';
                            input.classList.add('success');
                            input.dispatchEvent(new Event('change'));
                            
                            setTimeout(() => {
                                input.classList.remove('success');
                            }, 1000);
                        });
                        
                        dropdown.appendChild(div);
                    });
                    
                    dropdown.style.display = 'block';
                    dropdown.classList.add('slide-down');
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        removeLoadingIndicator(loadingIndicator);
                        dropdown.style.display = 'none';
                        console.error('Erro no autocomplete:', err);
                    }
                })
                .finally(() => {
                    delete activeRequests[inputId];
                });
        }, CONFIG.DEBOUNCE_DELAY);
    });

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
            dropdown.classList.remove('slide-down');
        }
    });

    // Navegação por teclado no dropdown
    input.addEventListener('keydown', e => {
        const items = dropdown.querySelectorAll('.autocomplete-item');
        if (!items.length) return;

        let currentIndex = Array.from(items).findIndex(item => 
            item.classList.contains('selected')
        );

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentIndex = (currentIndex + 1) % items.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
        } else if (e.key === 'Enter' && currentIndex >= 0) {
            e.preventDefault();
            items[currentIndex].click();
            return;
        } else {
            return;
        }

        items.forEach(item => item.classList.remove('selected'));
        items[currentIndex].classList.add('selected');
        items[currentIndex].scrollIntoView({ block: 'nearest' });
    });
}

// ---- Utilitários para Loading Indicator ----
function createLoadingIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'loading-spinner';
    indicator.style.cssText = `
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        display: none;
    `;
    return indicator;
}

function showLoadingIndicator(input, indicator) {
    if (!indicator.parentElement) {
        input.parentElement.appendChild(indicator);
    }
    indicator.style.display = 'inline-block';
}

function removeLoadingIndicator(indicator) {
    indicator.style.display = 'none';
}

// ---- Modal Terceira Contagem com Animações ----
function abrirModalTerceiraContagem(contagemId, primaria, secundaria) {
    const overlay = document.getElementById('modalTerceiraContagem');
    if (!overlay) return;

    document.getElementById('contagemId').value = contagemId;
    document.getElementById('primeiraContagemValor').textContent = primaria;
    document.getElementById('segundaContagemValor').textContent = secundaria;
    
    const terceiraInput = document.getElementById('quantidadeTerceira');
    terceiraInput.value = Math.round((primaria + secundaria) / 2);
    
    overlay.style.display = 'flex';
    overlay.classList.add('fade-in');
    
    setTimeout(() => {
        terceiraInput.focus();
        terceiraInput.select();
        terceiraInput.classList.add('pulse');
        setTimeout(() => terceiraInput.classList.remove('pulse'), 1000);
    }, CONFIG.ANIMATION_DURATION);
}

function fecharModal() {
    const overlay = document.getElementById('modalTerceiraContagem');
    if (!overlay) return;
    
    overlay.classList.add('fade-out');
    
    setTimeout(() => {
        overlay.style.display = 'none';
        overlay.classList.remove('fade-out', 'fade-in');
    }, CONFIG.ANIMATION_DURATION);
}

// ---- Toast Aprimorado com Ícones ----
function showToast(text, type = 'sucesso') {
    const icons = {
        sucesso: '✓',
        erro: '✕',
        aviso: '⚠',
        info: 'ℹ'
    };

    const toast = document.createElement('div');
    toast.className = `mensagem ${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 320px;
        box-shadow: 0 8px 16px rgba(0,0,0,.2);
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    const icon = document.createElement('span');
    icon.style.cssText = `
        font-size: 20px;
        font-weight: bold;
    `;
    icon.textContent = icons[type] || icons.info;
    
    const message = document.createElement('span');
    message.textContent = text;
    
    toast.appendChild(icon);
    toast.appendChild(message);
    document.body.appendChild(toast);
    
    // Animação de entrada
    toast.classList.add('slide-down');
    
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), CONFIG.ANIMATION_DURATION);
    }, CONFIG.TOAST_DURATION);

    // Fechar ao clicar
    toast.addEventListener('click', () => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), CONFIG.ANIMATION_DURATION);
    });

    // Adicionar cursor pointer
    toast.style.cursor = 'pointer';
}

// ---- Skeleton Loading para Tabelas ----
function showTableSkeleton(containerId, rows = 5) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const skeleton = document.createElement('div');
    skeleton.className = 'table-skeleton';
    skeleton.innerHTML = `
        ${Array(rows).fill('').map(() => `
            <div class="skeleton skeleton-text" style="margin: 10px 0;"></div>
        `).join('')}
    `;
    
    container.innerHTML = '';
    container.appendChild(skeleton);
}

function removeTableSkeleton(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const skeleton = container.querySelector('.table-skeleton');
    if (skeleton) {
        skeleton.classList.add('fade-out');
        setTimeout(() => skeleton.remove(), CONFIG.ANIMATION_DURATION);
    }
}

// ---- Utilitários ----
function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function confirmar(msg) {
    return confirm(msg);
}

// ---- Exportar funções globais ----
window.ButtonLoadingManager = ButtonLoadingManager;
window.showToast = showToast;
window.showTableSkeleton = showTableSkeleton;
window.removeTableSkeleton = removeTableSkeleton;