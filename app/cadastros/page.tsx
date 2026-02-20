import { redirect } from 'next/navigation'
import { getSession, isAdmin } from '@/lib/session'
import { depositoAll, partnumberAll } from '@/lib/models'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'

export const metadata = { title: 'Cadastros — Invento' }

const PER_PAGE = 20

export default async function CadastrosPage({
  searchParams,
}: {
  searchParams: { tipo?: string; msg?: string; msgType?: string; p_dep?: string; p_pn?: string }
}) {
  const session = await getSession()
  if (!session.usuarioId) redirect('/login')
  if (!isAdmin(session)) redirect('/dashboard')

  const tipo = ['depositos', 'partnumbers'].includes(searchParams.tipo || '')
    ? searchParams.tipo!
    : 'depositos'

  const depositos   = await depositoAll()
  const partnumbers = await partnumberAll()

  const message  = searchParams.msg    || ''
  const msgType  = searchParams.msgType || 'sucesso'

  // Paginação depósitos
  const pageDep    = Math.max(1, parseInt(searchParams.p_dep || '1'))
  const totalDep   = depositos.length
  const totalPagesDep = Math.ceil(totalDep / PER_PAGE)
  const depositosPaged = depositos.slice((pageDep - 1) * PER_PAGE, pageDep * PER_PAGE)

  // Paginação partnumbers
  const pagePN     = Math.max(1, parseInt(searchParams.p_pn || '1'))
  const totalPN    = partnumbers.length
  const totalPagesPN = Math.ceil(totalPN / PER_PAGE)
  const partnumbersPaged = partnumbers.slice((pagePN - 1) * PER_PAGE, pagePN * PER_PAGE)

  const unidades = ['UN', 'CX', 'KG', 'PC', 'MT', 'LT', 'PT']

  // Helper para montar URL de paginação mantendo os outros params
  function pageUrl(p_dep: number, p_pn: number) {
    return `/cadastros?tipo=${tipo}&p_dep=${p_dep}&p_pn=${p_pn}`
  }

  return (
    <>
      <Navbar session={session} currentPage="cadastros" />
      <main className="container">
        <div className="form-container">
          <h2 className="form-title"><i className="fas fa-database"></i> Cadastros</h2>

          {message && (
            <div className={`mensagem ${msgType}`} data-auto-hide>{message}</div>
          )}

          {/* Tabs */}
          <div className="tabs">
            <a href="/cadastros?tipo=depositos" className={`tab ${tipo === 'depositos' ? 'active' : ''}`}>
              <i className="fas fa-warehouse"></i> Depósitos ({totalDep})
            </a>
            <a href="/cadastros?tipo=partnumbers" className={`tab ${tipo === 'partnumbers' ? 'active' : ''}`}>
              <i className="fas fa-barcode"></i> Part Numbers ({totalPN})
            </a>
          </div>

          {tipo === 'depositos' ? (
            <>
              {/* Formulário Depósito */}
              <form id="formDeposito" style={{ marginBottom: '25px' }}>
                <h3 className="form-title" style={{ fontSize: '16px' }}><i className="fas fa-plus"></i> Cadastrar Depósito</h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', gap: '14px', alignItems: 'end' }} className="form-grid-auto">
                  <div className="form-group" style={{ marginBottom: 0 }}>
                    <label>Nome do Depósito:</label>
                    <input type="text" name="deposito" required placeholder="Ex: ALMOX-01" id="inputDeposito" />
                  </div>
                  <div className="form-group" style={{ marginBottom: 0 }}>
                    <label>Localização <small style={{ fontWeight: 400, color: 'var(--gray)' }}>(opcional)</small>:</label>
                    <input type="text" name="localizacao" placeholder="Ex: Prédio A, Galpão 2" />
                  </div>
                  <button type="submit" className="btn btn-primary" id="btnSalvarDeposito">
                    <span className="btn-text"><i className="fas fa-save"></i> Salvar</span>
                  </button>
                </div>
              </form>

              {/* Tabela Depósitos */}
              <div className="table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Depósito</th>
                      <th>Localização</th>
                      <th>Registros</th>
                      <th>Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    {depositosPaged.length === 0 ? (
                      <tr>
                        <td colSpan={4} style={{ textAlign: 'center', padding: '25px', color: 'var(--gray)' }}>
                          Nenhum depósito cadastrado.
                        </td>
                      </tr>
                    ) : (
                      depositosPaged.map(dep => (
                        <tr key={dep.deposito}>
                          <td><strong>{dep.deposito}</strong></td>
                          <td>{dep.localizacao || '—'}</td>
                          <td>{Math.max(0, dep.total_registros - 1)}</td>
                          <td>
                            <button
                              className="btn btn-sm btn-danger btn-excluir-deposito"
                              data-deposito={dep.deposito}
                              data-nome={dep.deposito}
                            >
                              <span className="btn-text"><i className="fas fa-trash"></i></span>
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>

              {/* Paginação Depósitos */}
              {totalPagesDep > 1 && (
                <div className="pagination" style={{ marginTop: '16px' }}>
                  {pageDep > 1 && (
                    <a href={pageUrl(pageDep - 1, pagePN)}><i className="fas fa-chevron-left"></i></a>
                  )}
                  {Array.from({ length: totalPagesDep }, (_, i) => i + 1)
                    .filter(i => Math.abs(i - pageDep) <= 2)
                    .map(i => (
                      i === pageDep
                        ? <span key={i} className="current">{i}</span>
                        : <a key={i} href={pageUrl(i, pagePN)}>{i}</a>
                    ))}
                  {pageDep < totalPagesDep && (
                    <a href={pageUrl(pageDep + 1, pagePN)}><i className="fas fa-chevron-right"></i></a>
                  )}
                </div>
              )}
              {totalDep > 0 && (
                <p style={{ fontSize: '12px', color: 'var(--gray)', marginTop: '8px', textAlign: 'right' }}>
                  Exibindo {(pageDep - 1) * PER_PAGE + 1}–{Math.min(pageDep * PER_PAGE, totalDep)} de {totalDep} depósitos
                </p>
              )}
            </>
          ) : (
            <>
              {/* Formulário Part Number */}
              <form id="formPartnumber" style={{ marginBottom: '25px' }}>
                <h3 className="form-title" style={{ fontSize: '16px' }}><i className="fas fa-plus"></i> Cadastrar Part Number</h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr 1fr auto', gap: '14px', alignItems: 'end' }} className="form-grid-auto">
                  <div className="form-group" style={{ marginBottom: 0 }}>
                    <label>Part Number:</label>
                    <input type="text" name="partnumber" required placeholder="Ex: ABC-123" />
                  </div>
                  <div className="form-group" style={{ marginBottom: 0 }}>
                    <label>Descrição <small style={{ fontWeight: 400, color: 'var(--gray)' }}>(opcional)</small>:</label>
                    <input type="text" name="descricao" placeholder="Descrição do item" />
                  </div>
                  <div className="form-group" style={{ marginBottom: 0 }}>
                    <label>Unidade:</label>
                    <select name="unidade" required>
                      {unidades.map(u => <option key={u} value={u}>{u}</option>)}
                    </select>
                  </div>
                  <button type="submit" className="btn btn-primary" id="btnSalvarPN">
                    <span className="btn-text"><i className="fas fa-save"></i> Salvar</span>
                  </button>
                </div>
              </form>

              {/* Importar CSV */}
              <details style={{ marginBottom: '25px' }}>
                <summary style={{ cursor: 'pointer', fontWeight: 600, color: 'var(--secondary)', padding: '10px', background: '#f0f8ff', borderRadius: 'var(--border-r)' }}>
                  <i className="fas fa-file-import"></i> Importar via CSV
                </summary>
                <div style={{ padding: '18px', background: '#f8f9fa', borderRadius: '0 0 var(--border-r) var(--border-r)', border: '1px solid #e0e0e0', borderTop: 'none' }}>
                  <p style={{ fontSize: '13px', color: 'var(--gray)', marginBottom: '12px' }}>
                    Formato: <code>partnumber;descricao;unidade</code> — uma entrada por linha, com cabeçalho.
                  </p>
                  <form id="formImportarCSV" style={{ display: 'flex', gap: '12px', alignItems: 'flex-end', flexWrap: 'wrap' }}>
                    <div>
                      <label style={{ fontSize: '13px', marginBottom: '6px', display: 'block' }}>Arquivo CSV:</label>
                      <input type="file" name="arquivo_csv" accept=".csv,.txt" required />
                    </div>
                    <button type="submit" className="btn btn-secondary" id="btnImportarCSV">
                      <span className="btn-text"><i className="fas fa-upload"></i> Importar</span>
                    </button>
                  </form>
                </div>
              </details>

              {/* Tabela Part Numbers */}
              <div className="table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Part Number</th>
                      <th>Descrição</th>
                      <th>Unidade</th>
                      <th>Registros</th>
                      <th>Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    {partnumbersPaged.length === 0 ? (
                      <tr>
                        <td colSpan={5} style={{ textAlign: 'center', padding: '25px', color: 'var(--gray)' }}>
                          Nenhum part number cadastrado.
                        </td>
                      </tr>
                    ) : (
                      partnumbersPaged.map(pn => (
                        <tr key={pn.partnumber}>
                          <td><strong>{pn.partnumber}</strong></td>
                          <td>{pn.descricao || '—'}</td>
                          <td><span className="badge badge-info">{pn.unidade_medida || 'UN'}</span></td>
                          <td>{pn.total_registros}</td>
                          <td>
                            <button
                              className="btn btn-sm btn-danger btn-excluir-pn"
                              data-pn={pn.partnumber}
                              data-nome={pn.partnumber}
                            >
                              <span className="btn-text"><i className="fas fa-trash"></i></span>
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>

              {/* Paginação Part Numbers */}
              {totalPagesPN > 1 && (
                <div className="pagination" style={{ marginTop: '16px' }}>
                  {pagePN > 1 && (
                    <a href={pageUrl(pageDep, pagePN - 1)}><i className="fas fa-chevron-left"></i></a>
                  )}
                  {Array.from({ length: totalPagesPN }, (_, i) => i + 1)
                    .filter(i => Math.abs(i - pagePN) <= 2)
                    .map(i => (
                      i === pagePN
                        ? <span key={i} className="current">{i}</span>
                        : <a key={i} href={pageUrl(pageDep, i)}>{i}</a>
                    ))}
                  {pagePN < totalPagesPN && (
                    <a href={pageUrl(pageDep, pagePN + 1)}><i className="fas fa-chevron-right"></i></a>
                  )}
                </div>
              )}
              {totalPN > 0 && (
                <p style={{ fontSize: '12px', color: 'var(--gray)', marginTop: '8px', textAlign: 'right' }}>
                  Exibindo {(pagePN - 1) * PER_PAGE + 1}–{Math.min(pagePN * PER_PAGE, totalPN)} de {totalPN} part numbers
                </p>
              )}
            </>
          )}
        </div>

        <script dangerouslySetInnerHTML={{ __html: `
          async function postCadastro(fd, redirectUrl) {
            const resp = await fetch('/api/cadastros', { method: 'POST', body: fd });
            const data = await resp.json();
            const ok   = data.success !== false && !data.error;
            const msg  = data.message || data.error || (ok ? 'Operação realizada.' : 'Erro.');
            window.location.href = redirectUrl + '&msg=' + encodeURIComponent(msg) + '&msgType=' + (ok ? 'sucesso' : 'erro');
          }

          document.addEventListener('DOMContentLoaded', function() {
            const tipo = '${tipo}';

            // Cadastrar depósito
            const formDep = document.getElementById('formDeposito');
            if (formDep) {
              formDep.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnSalvarDeposito');
                if (btn) btn.disabled = true;
                const fd = new FormData(formDep);
                fd.append('acao', 'cadastrar_deposito');
                await postCadastro(fd, '/cadastros?tipo=depositos');
              });
            }

            // Excluir depósito
            document.querySelectorAll('.btn-excluir-deposito').forEach(btn => {
              btn.addEventListener('click', async function() {
                const nome = this.dataset.nome;
                if (!confirm('Excluir o depósito \\'' + nome + '\\'?')) return;
                this.disabled = true;
                const fd = new FormData();
                fd.append('acao', 'excluir_deposito');
                fd.append('deposito', this.dataset.deposito);
                await postCadastro(fd, '/cadastros?tipo=depositos');
              });
            });

            // Cadastrar partnumber
            const formPN = document.getElementById('formPartnumber');
            if (formPN) {
              formPN.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnSalvarPN');
                if (btn) btn.disabled = true;
                const fd = new FormData(formPN);
                fd.append('acao', 'cadastrar_partnumber');
                await postCadastro(fd, '/cadastros?tipo=partnumbers');
              });
            }

            // Excluir part number
            document.querySelectorAll('.btn-excluir-pn').forEach(btn => {
              btn.addEventListener('click', async function() {
                const nome = this.dataset.nome;
                if (!confirm('Excluir o part number \\'' + nome + '\\'?')) return;
                this.disabled = true;
                const fd = new FormData();
                fd.append('acao', 'excluir_partnumber');
                fd.append('partnumber', this.dataset.pn);
                await postCadastro(fd, '/cadastros?tipo=partnumbers');
              });
            });

            // Importar CSV
            const formCSV = document.getElementById('formImportarCSV');
            if (formCSV) {
              formCSV.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnImportarCSV');
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="btn-text"><i class="fas fa-spinner fa-spin"></i> Importando...</span>'; }
                const fd = new FormData(formCSV);
                fd.append('acao', 'importar_partnumbers');
                await postCadastro(fd, '/cadastros?tipo=partnumbers');
              });
            }
          });
        `}} />
      </main>
      <Footer />
    </>
  )
}
