import { redirect } from 'next/navigation'
import { getSession } from '@/lib/session'

export const metadata = { title: 'Login — Invento' }

export default async function LoginPage({
  searchParams,
}: {
  searchParams: { timeout?: string; error?: string }
}) {
  const session = await getSession()
  if (session.usuarioId) {
    redirect('/dashboard')
  }

  return (
    <>
      <main className="container">
        <div className="login-container">
          <div className="login-box">
            <div className="login-header">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src="/assets/Ivento.png" style={{ height: '300px' }} alt="Invento" />
            </div>

            {searchParams.error && (
              <div className="mensagem erro" data-auto-hide>{searchParams.error}</div>
            )}
            {searchParams.timeout && (
              <div className="mensagem aviso" data-auto-hide>
                <i className="fas fa-clock"></i> Sessão expirada. Por favor, faça login novamente.
              </div>
            )}

            <form id="loginForm" action="" method="post">
              <div className="form-group">
                <label htmlFor="nome"><i className="fas fa-user"></i> Nome Completo:</label>
                <input
                  type="text"
                  id="nome"
                  name="nome"
                  required
                  placeholder="Digite seu nome completo"
                  autoComplete="name"
                />
              </div>

              <div className="form-group">
                <label htmlFor="matricula"><i className="fas fa-id-card"></i> Matrícula:</label>
                <input
                  type="text"
                  id="matricula"
                  name="matricula"
                  required
                  placeholder="Digite sua matrícula"
                  autoComplete="username"
                />
              </div>

              <div id="passwordFieldGroup" className="form-group" style={{ display: 'none' }}>
                <label htmlFor="senha">
                  <i className="fas fa-lock"></i> Senha{' '}
                  <small style={{ fontWeight: 400, color: 'var(--gray)' }}>(obrigatório para administrador)</small>:
                </label>
                <input
                  type="password"
                  id="senha"
                  name="senha"
                  placeholder="Senha do administrador"
                  autoComplete="current-password"
                />
              </div>

              <button type="submit" className="btn btn-primary" style={{ width: '100%', justifyContent: 'center', marginTop: '8px' }}>
                <i className="fas fa-sign-in-alt"></i> Acessar Sistema
              </button>
            </form>

            <div style={{ marginTop: '24px', padding: '18px', background: '#f8f9fa', borderRadius: 'var(--border-r)', textAlign: 'left', fontSize: '13px', color: 'var(--gray)' }}>
              <strong style={{ color: 'var(--primary)', display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '10px' }}>
                <i className="fas fa-info-circle"></i> Informações de Acesso
              </strong>
              <ul style={{ paddingLeft: '18px', lineHeight: '1.8' }}>
                <li><strong>Operadores:</strong> apenas nome e matrícula</li>
                <li><strong>Dúvidas:</strong> contate o supervisor</li>
              </ul>
            </div>
          </div>
        </div>
      </main>

      <script dangerouslySetInnerHTML={{ __html: `
        (function() {
          'use strict';

          const loginForm     = document.getElementById('loginForm');
          const nomeInput     = document.getElementById('nome');
          const matriculaInput = document.getElementById('matricula');
          const passwordGroup = document.getElementById('passwordFieldGroup');
          const passwordInput = document.getElementById('senha');

          function togglePasswordField() {
            const nome      = nomeInput.value.trim().toLowerCase();
            const matricula = matriculaInput.value.trim().toLowerCase();
            const deveMostrar = (nome === 'administrador' && matricula === 'admin');

            if (deveMostrar) {
              passwordGroup.style.display = 'block';
              passwordInput.setAttribute('required', 'required');
            } else {
              passwordGroup.style.display = 'none';
              passwordInput.removeAttribute('required');
              passwordInput.value = '';
            }
          }

          nomeInput.addEventListener('input', togglePasswordField);
          matriculaInput.addEventListener('input', togglePasswordField);
          document.addEventListener('DOMContentLoaded', togglePasswordField);

          loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = loginForm.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...'; }

            const fd = new FormData(loginForm);
            try {
              const resp = await fetch('/api/auth/login', { method: 'POST', body: fd });
              const data = await resp.json();
              if (data.success) {
                window.location.href = data.redirectTo;
              } else {
                const err = data.error || 'Erro ao fazer login.';
                let el = document.querySelector('.mensagem.erro');
                if (!el) {
                  el = document.createElement('div');
                  el.className = 'mensagem erro';
                  loginForm.parentNode.insertBefore(el, loginForm);
                }
                el.textContent = err;
                el.style.display = '';
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class=\\'fas fa-sign-in-alt\\'></i> Acessar Sistema'; }
              }
            } catch(ex) {
              if (btn) { btn.disabled = false; btn.innerHTML = '<i class=\\'fas fa-sign-in-alt\\'></i> Acessar Sistema'; }
            }
          });
        })();
      `}} />
    </>
  )
}
