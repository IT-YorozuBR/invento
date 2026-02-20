import { redirect } from 'next/navigation'
import { getSession, isAdmin } from '@/lib/session'
import { inventarioFindConcluidos } from '@/lib/models'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'

export const metadata = { title: 'Inventários Concluídos — Invento' }

export default async function InventariosConcluidos({
  searchParams,
}: {
  searchParams: { p?: string; msg?: string; msgType?: string }
}) {
  const session = await getSession()
  if (!session.usuarioId) redirect('/login')
  if (!isAdmin(session)) redirect('/dashboard')

  const page = Math.max(1, parseInt(searchParams.p || '1'))
  const data = await inventarioFindConcluidos(page)
  const message = searchParams.msg || ''
  const msgType = searchParams.msgType || 'sucesso'


  const fmtDate = (d: string | Date | null) => {
    if (!d) return '—'
    const dt = d instanceof Date ? d : new Date(String(d).slice(0, 10) + 'T12:00:00')
    return isNaN(dt.getTime()) ? '—' : dt.toLocaleDateString('pt-BR')
  }
  return (
    <>
      <Navbar session={session} currentPage="inventarios-concluidos" />
      <main className="container">
        <div className="form-container">
          <h2 className="form-title">
            <i className="fas fa-history"></i> Inventários Concluídos
            <span style={{ fontSize: '13px', color: 'var(--gray)', fontWeight: 400, marginLeft: 'auto' }}>
              {data.total} registro(s)
            </span>
          </h2>

          {message && (
            <div className={`mensagem ${msgType}`} data-auto-hide>{message}</div>
          )}

          {data.items.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '40px', color: 'var(--gray)' }}>
              <i className="fas fa-inbox" style={{ fontSize: '40px', marginBottom: '15px', display: 'block' }}></i>
              Nenhum inventário concluído ainda.
            </div>
          ) : (
            <>
              <div className="table-container">
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
                    {data.items.map(inv => (
                      <tr key={inv.id}>
                        <td><strong>{inv.codigo}</strong></td>
                        <td>{inv.descricao || '—'}</td>
                        <td>{fmtDate(inv.data_inicio)}</td>
                        <td>{fmtDate(inv.data_fim)}</td>
                        <td>{(inv as any).admin_nome || '—'}</td>
                        <td>
                          <span className={`status-badge ${inv.status === 'fechado' ? 'status-concluida' : 'status-divergente'}`}>
                            {inv.status}
                          </span>
                        </td>
                        <td>
                          <button
                            className="btn btn-sm btn-outline btn-exportar-toggle"
                            data-inv-id={inv.id}
                            type="button"
                          >
                            <i className="fas fa-file-export"></i> Exportar <i className="fas fa-chevron-down" style={{ fontSize: '10px' }}></i>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Paginação */}
              {data.total_pages > 1 && (
                <div className="pagination">
                  {data.page > 1 && (
                    <a href={`/inventarios-concluidos?p=${data.page - 1}`}>
                      <i className="fas fa-chevron-left"></i>
                    </a>
                  )}
                  {Array.from({ length: data.total_pages }, (_, i) => i + 1)
                    .filter(i => Math.abs(i - data.page) <= 2)
                    .map(i => (
                      i === data.page
                        ? <span key={i} className="current">{i}</span>
                        : <a key={i} href={`/inventarios-concluidos?p=${i}`}>{i}</a>
                    ))}
                  {data.page < data.total_pages && (
                    <a href={`/inventarios-concluidos?p=${data.page + 1}`}>
                      <i className="fas fa-chevron-right"></i>
                    </a>
                  )}
                </div>
              )}
            </>
          )}
        </div>
      </main>

      {/* Dropdown flutuante — renderizado fora da tabela, no body */}
      <div id="exportDropdown" style={{
        display: 'none',
        position: 'fixed',
        zIndex: 9999,
        background: 'white',
        border: '1px solid #e2e8f0',
        borderRadius: '8px',
        boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
        minWidth: '160px',
        overflow: 'hidden',
      }}>
        <a id="exportXlsx" href="#" style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '10px 16px', color: '#1e293b', textDecoration: 'none', fontSize: '14px', borderBottom: '1px solid #f1f5f9' }}
          onMouseEnter={undefined}>
          <i className="fas fa-file-excel" style={{ color: 'var(--success)' }}></i> Excel (XLSX)
        </a>
        <a id="exportCsv" href="#" style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '10px 16px', color: '#1e293b', textDecoration: 'none', fontSize: '14px', borderBottom: '1px solid #f1f5f9' }}>
          <i className="fas fa-file-csv" style={{ color: 'var(--secondary)' }}></i> CSV
        </a>
        <a id="exportTxt" href="#" style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '10px 16px', color: '#1e293b', textDecoration: 'none', fontSize: '14px' }}>
          <i className="fas fa-file-alt" style={{ color: 'var(--gray)' }}></i> TXT
        </a>
      </div>

      <script dangerouslySetInnerHTML={{
        __html: `
        (function() {
          var dropdown = document.getElementById('exportDropdown');
          var activeBtn = null;

          function fecharDropdown() {
            dropdown.style.display = 'none';
            activeBtn = null;
          }

          document.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-exportar-toggle');

            if (btn) {
              e.stopPropagation();

              // Se já está aberto para este botão, fecha
              if (activeBtn === btn) {
                fecharDropdown();
                return;
              }

              activeBtn = btn;
              var invId = btn.dataset.invId;

              // Atualiza os links de exportação
              document.getElementById('exportXlsx').href = '/api/exportar?inventario_id=' + invId + '&formato=xlsx';
              document.getElementById('exportCsv').href  = '/api/exportar?inventario_id=' + invId + '&formato=csv';
              document.getElementById('exportTxt').href  = '/api/exportar?inventario_id=' + invId + '&formato=txt';

              // Posiciona o dropdown usando fixed — imune ao overflow da tabela
              var rect = btn.getBoundingClientRect();
              dropdown.style.display = 'block';

              var dropH = dropdown.offsetHeight;
              var spaceBelow = window.innerHeight - rect.bottom;

              if (spaceBelow < dropH + 8) {
                // Abre para cima
                dropdown.style.top  = (rect.top - dropH - 4) + 'px';
              } else {
                // Abre para baixo
                dropdown.style.top  = (rect.bottom + 4) + 'px';
              }

              // Alinha à direita do botão
              var left = rect.right - dropdown.offsetWidth;
              if (left < 8) left = 8;
              dropdown.style.left = left + 'px';

              return;
            }

            // Clicou fora — fecha
            if (!e.target.closest('#exportDropdown')) {
              fecharDropdown();
            }
          });

          // Fecha ao rolar a página
          window.addEventListener('scroll', fecharDropdown, true);
        })();
      `}} />

      <Footer />
    </>
  )
}