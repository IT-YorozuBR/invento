import { redirect } from 'next/navigation'
import { getSession, isAdmin } from '@/lib/session'
import { inventarioFindAtivo, inventarioGetEstatisticas } from '@/lib/models'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'
import ModalFooter from '@/components/ModalFooter'

export const metadata = { title: 'Dashboard — Invento' }

export default async function DashboardPage({
  searchParams,
}: {
  searchParams: { msg?: string; msgType?: string }
}) {
  const session = await getSession()
  if (!session.usuarioId) redirect('/login')

  // Operadores com inventário ativo vão para contagem
  if (!isAdmin(session)) {
    const ativo = await inventarioFindAtivo()
    if (ativo) redirect('/contagem')
  }

  const inventario = await inventarioFindAtivo()
  const stats = inventario ? await inventarioGetEstatisticas(inventario.id) : null
  const message = searchParams.msg || ''
  const msgType = searchParams.msgType || 'sucesso'

  // Build CSRF token (using random for this session)
  const csrfToken = Math.random().toString(36).slice(2)
  const fmtDate = (d: string | Date | null) => {
    if (!d) return '—'
    // Pega apenas a data (YYYY-MM-DD) e adiciona meio-dia UTC
    const dateStr = d instanceof Date
      ? d.toISOString().split('T')[0]
      : String(d).split('T')[0]
    const dt = new Date(dateStr + 'T12:00:00Z')
    return isNaN(dt.getTime()) ? '—' : dt.toLocaleDateString('pt-BR', { timeZone: 'America/Sao_Paulo' })
  }
  return (
    <>
      <Navbar session={session} currentPage="dashboard" />
      <main className="container">
        <div className="form-container">
          <h2 className="form-title">
            <i className="fas fa-tachometer-alt"></i> Dashboard
            {inventario && (
              <span style={{ fontSize: '13px', color: 'var(--gray)', fontWeight: 400, marginLeft: 'auto' }}>
                Inventário ativo: <strong>{inventario.codigo}</strong>
              </span>
            )}
          </h2>

          {message && (
            <div className={`mensagem ${msgType}`} data-auto-hide>{message}</div>
          )}

          {/* Status do inventário */}
          {inventario ? (
            <div className="form-container" style={{ background: 'linear-gradient(to right,#f8fff9,white)', borderLeft: '5px solid var(--success)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
                <div>
                  <h3 style={{ color: 'var(--success)', marginBottom: '8px' }}><i className="fas fa-play-circle"></i> Inventário em Andamento</h3>
                  <p style={{ color: 'var(--gray)', fontSize: '14px' }}>
                    <strong>Código:</strong> {inventario.codigo} &nbsp;&bull;&nbsp;
                    <strong>Data:</strong> {fmtDate(inventario.data_inicio)} &nbsp;&bull;&nbsp;
                    <strong>Descrição:</strong> {inventario.descricao}
                  </p>
                </div>
                <span style={{ background: 'var(--success)', color: 'white', padding: '7px 16px', borderRadius: '20px', fontWeight: 700, fontSize: '12px' }}>
                  <i className="fas fa-circle"></i> ABERTO
                </span>
              </div>
            </div>
          ) : (
            <div className="form-container" style={{ background: 'linear-gradient(to right,#fff8f8,white)', borderLeft: '5px solid var(--danger)' }}>
              <div style={{ textAlign: 'center', padding: '20px' }}>
                <h3 style={{ color: 'var(--danger)', marginBottom: '10px' }}><i className="fas fa-pause-circle"></i> Nenhum Inventário Ativo</h3>
                <p style={{ color: 'var(--gray)' }}>
                  {isAdmin(session)
                    ? 'Crie um novo inventário para iniciar as contagens.'
                    : 'Aguarde o administrador iniciar um inventário.'}
                </p>
              </div>
            </div>
          )}

          {/* Cards de estatísticas */}
          {inventario && stats && (
            <div className="cards-container">
              <div className="card">
                <h3><i className="fas fa-clipboard-list"></i> Total de Contagens</h3>
                <div className="value">{stats.total}</div>
                <p style={{ color: 'var(--gray)', fontSize: '12px', marginTop: '5px' }}>registradas</p>
              </div>
              <div className="card" style={{ borderTopColor: 'var(--success)' }}>
                <h3><i className="fas fa-check-circle"></i> Concluídas</h3>
                <div className="value" style={{ color: 'var(--success)' }}>{stats.concluidas}</div>
                <p style={{ color: 'var(--gray)', fontSize: '12px', marginTop: '5px' }}>validadas</p>
              </div>
              <div className="card" style={{ borderTopColor: 'var(--danger)' }}>
                <h3><i className="fas fa-exclamation-triangle"></i> Divergentes</h3>
                <div className="value" style={{ color: 'var(--danger)' }}>{stats.divergentes}</div>
                <p style={{ color: 'var(--gray)', fontSize: '12px', marginTop: '5px' }}>necessitam atenção</p>
              </div>
              <div className="card" style={{ borderTopColor: 'var(--warning)' }}>
                <h3><i className="fas fa-clock"></i> Pendentes</h3>
                <div className="value" style={{ color: 'var(--warning)' }}>{stats.pendentes}</div>
                <p style={{ color: 'var(--gray)', fontSize: '12px', marginTop: '5px' }}>aguardando 2ª contagem</p>
              </div>
              {(stats.terceiras > 0) && (
                <div className="card" style={{ borderTopColor: 'var(--info)' }}>
                  <h3><i className="fas fa-users"></i> 3ª Contagem</h3>
                  <div className="value" style={{ color: 'var(--info)' }}>{stats.terceiras}</div>
                  <p style={{ color: 'var(--gray)', fontSize: '12px', marginTop: '5px' }}>aguardando 3ª contagem</p>
                </div>
              )}
              <div className="card" style={{ borderTopColor: 'var(--secondary)' }}>
                <h3><i className="fas fa-barcode"></i> Part Numbers</h3>
                <div className="value">{stats.partnumbers}</div>
                <p style={{ color: 'var(--gray)', fontSize: '12px', marginTop: '5px' }}>distintos</p>
              </div>
            </div>
          )}

          {/* Ações de admin */}
          {isAdmin(session) && (
            <div className="form-container">
              <h3 className="form-title"><i className="fas fa-cog"></i> Gerenciar Inventário</h3>

              {!inventario ? (
                <form id="formCriarInventario" onSubmit={undefined}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '20px' }} className="form-grid">
                    <div className="form-group">
                      <label htmlFor="data_inicio"><i className="fas fa-calendar"></i> Data do Inventário:</label>
                      <input
                        type="date"
                        id="data_inicio"
                        name="data_inicio"
                        required
                        defaultValue={new Date().toISOString().slice(0, 10)}
                        min={new Date().toISOString().slice(0, 10)}
                      />
                    </div>
                    <div className="form-group">
                      <label htmlFor="descricao"><i className="fas fa-file-alt"></i> Descrição:</label>
                      <input type="text" id="descricao" name="descricao" required placeholder="Ex: Inventário Trimestral — Setor A" />
                    </div>
                  </div>
                  <button type="submit" className="btn btn-primary" id="btnCriarInventario">
                    <span className="btn-text"><i className="fas fa-plus-circle"></i> Criar Novo Inventário</span>
                  </button>
                </form>
              ) : (
                <>
                  <div style={{ background: '#f8f9fa', padding: '18px', borderRadius: 'var(--border-r)', marginBottom: '20px', display: 'grid', gap: '8px', fontSize: '14px' }}>
                    {[
                      ['var(--success)', 'Concluídas', stats?.concluidas ?? 0],
                      ['var(--danger)', 'Divergentes', stats?.divergentes ?? 0],
                      ['var(--warning)', 'Pendentes', stats?.pendentes ?? 0],
                    ].map(([cor, label, val]) => (
                      <div key={String(label)} style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: String(cor), flexShrink: 0 }}></div>
                        <span>{String(label)}: <strong>{String(val)}</strong></span>
                      </div>
                    ))}
                  </div>

                  <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                    <form id="formFecharInventario">
                      <input type="hidden" name="inventario_id" value={inventario.id} />
                      <button type="submit" className="btn btn-danger" id="btnFecharInventario">
                        <span className="btn-text"><i className="fas fa-lock"></i> Fechar Inventário</span>
                      </button>
                    </form>

                    <div className="dropdown">
                      <button className="btn btn-outline dropdown-toggle" type="button">
                        <i className="fas fa-file-export"></i> Exportar <i className="fas fa-chevron-down" style={{ fontSize: '11px' }}></i>
                      </button>
                      <div className="dropdown-menu">
                        <a href={`/api/exportar?inventario_id=${inventario.id}&formato=xlsx`}>
                          <i className="fas fa-file-excel" style={{ color: 'var(--success)' }}></i> Excel (XLSX)
                        </a>
                        <a href={`/api/exportar?inventario_id=${inventario.id}&formato=csv`}>
                          <i className="fas fa-file-csv" style={{ color: 'var(--secondary)' }}></i> CSV
                        </a>
                        <a href={`/api/exportar?inventario_id=${inventario.id}&formato=txt`}>
                          <i className="fas fa-file-alt" style={{ color: 'var(--gray)' }}></i> TXT
                        </a>
                      </div>
                    </div>

                    <a href="/contagem" className="btn btn-secondary">
                      <i className="fas fa-clipboard-check"></i> Ver Contagens
                    </a>
                  </div>
                </>
              )}
            </div>
          )}
        </div>

        <ModalFooter csrfToken={csrfToken} />

        <script dangerouslySetInnerHTML={{
          __html: `
          (function() {
            // Criar inventário
            const formCriar = document.getElementById('formCriarInventario');
            if (formCriar) {
              formCriar.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnCriarInventario');
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="btn-text"><i class="fas fa-spinner fa-spin"></i> Criando...</span>'; }
                const fd = new FormData(formCriar);
                fd.append('acao_inventario', 'criar');
                const resp = await fetch('/api/dashboard', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                  window.location.href = '/dashboard?msg=' + encodeURIComponent(data.message) + '&msgType=sucesso';
                } else {
                  window.location.href = '/dashboard?msg=' + encodeURIComponent(data.error || data.message || 'Erro') + '&msgType=erro';
                }
              });
            }

            // Fechar inventário
            const formFechar = document.getElementById('formFecharInventario');
            if (formFechar) {
              formFechar.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!confirm('Fechar este inventário?\\n\\nApós fechar, novas contagens não poderão ser registradas.')) return;
                const btn = document.getElementById('btnFecharInventario');
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="btn-text"><i class="fas fa-spinner fa-spin"></i> Fechando...</span>'; }
                const fd = new FormData(formFechar);
                fd.append('acao_inventario', 'fechar');
                const resp = await fetch('/api/dashboard', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                  window.location.href = '/dashboard?msg=' + encodeURIComponent(data.message) + '&msgType=sucesso';
                } else {
                  window.location.href = '/dashboard?msg=' + encodeURIComponent(data.error || data.message || 'Erro') + '&msgType=erro';
                }
              });
            }
          })();
        `}} />
      </main>
      <Footer />
    </>
  )
}
