import { redirect } from 'next/navigation'
import { getSession, isAdmin as checkAdmin } from '@/lib/session'
import {
  inventarioFindAtivo,
  inventarioGetEstatisticas,
  contagemFindPaginated,
  depositoAll,
  partnumberAll,
} from '@/lib/models'
import type { Contagem } from '@/lib/models'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'

export const metadata = { title: 'Contagem — Invento' }

function fmtQtd(val: number | null, compare?: number | null): string {
  if (val === null) return '<span style="color:#cbd5e1">—</span>'
  const num = val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  if (compare !== undefined && compare !== null) {
    if (Math.abs(val - compare) < 0.0001) {
      return `<span class='qtd-match'>${num}<span class='qtd-icon'>✓</span></span>`
    } else {
      return `<span class='qtd-diff'>${num}<span class='qtd-icon'>≠</span></span>`
    }
  }
  return `<span class='qtd-cell'>${num}</span>`
}

function getRowClass(c: Contagem): string {
  const finalizado = !!c.finalizado
  const status = c.status
  const qtd1 = c.quantidade_primaria !== null ? Number(c.quantidade_primaria) : null
  const qtd2 = c.quantidade_secundaria !== null ? Number(c.quantidade_secundaria) : null
  const match12 = qtd2 !== null && qtd1 !== null && Math.abs(qtd1 - qtd2) < 0.0001

  if (status === 'divergente') return 'linha-divergente'
  if (status === 'concluida') return 'linha-match'
  if (finalizado) return 'linha-encerrado'
  if (qtd2 !== null && match12) return 'linha-match'
  if (qtd2 !== null && !match12) return 'linha-diff'
  return 'linha-primaria'
}

function getBadge(c: Contagem): string {
  const finalizado = !!c.finalizado
  const numContagens = c.numero_contagens_realizadas
  const status = c.status
  const podeNova = !!c.pode_nova_contagem

  // status do banco tem prioridade absoluta
  if (status === 'divergente') return '<span class="status-badge status-divergente"><i class="fas fa-exclamation-triangle"></i> Divergente</span>'
  if (status === 'concluida') return '<span class="status-badge status-concluida"><i class="fas fa-check-circle"></i> Concluída</span>'
  if (finalizado) return '<span class="status-badge status-encerrado"><i class="fas fa-lock"></i> Encerrado</span>'
  if (podeNova) {
    if (numContagens === 1) return '<span class="status-badge status-secundaria"><i class="fas fa-unlock"></i> Aguardando 2ª contagem</span>'
    if (numContagens === 2) return '<span class="status-badge status-secundaria"><i class="fas fa-unlock"></i> Aguardando 3ª contagem</span>'
    return '<span class="status-badge status-primaria"><i class="fas fa-clock"></i> Em andamento</span>'
  }
  const map: Record<string, string> = {
    primaria: '<span class="status-badge status-primaria"><i class="fas fa-clock"></i> Em andamento</span>',
    secundaria: '<span class="status-badge status-secundaria"><i class="fas fa-layer-group"></i> 2ª Contagem</span>',
  }
  return map[status] || `<span class="status-badge status-encerrado">${status}</span>`
}

export default async function ContagemPage({
  searchParams,
}: {
  searchParams: {
    p?: string
    status?: string
    partnumber?: string
    deposito?: string
    msg?: string
    msgType?: string
  }
}) {
  const session = await getSession()
  if (!session.usuarioId) redirect('/login')

  const inventarioAtivo = await inventarioFindAtivo()
  if (!inventarioAtivo) {
    redirect('/dashboard?msg=' + encodeURIComponent('Não há inventário ativo no momento.') + '&msgType=erro')
  }

  const isAdm = checkAdmin(session)
  const page = Math.max(1, parseInt(searchParams.p || '1'))
  const filters = {
    status: searchParams.status || '',
    partnumber: searchParams.partnumber || '',
    deposito: searchParams.deposito || '',
  }
  const pagination = await contagemFindPaginated(inventarioAtivo.id, page, filters)
  const stats = await inventarioGetEstatisticas(inventarioAtivo.id)
  const message = searchParams.msg || ''
  const msgType = searchParams.msgType || 'sucesso'

  // Build pagination URL base
  const filterQuery = [
    filters.partnumber ? `partnumber=${encodeURIComponent(filters.partnumber)}` : '',
    filters.deposito ? `deposito=${encodeURIComponent(filters.deposito)}` : '',
    filters.status ? `status=${encodeURIComponent(filters.status)}` : '',
  ].filter(Boolean).join('&')

  const paginaBase = '/contagem' + (filterQuery ? '?' + filterQuery : '')

  // Gerar CSRF token
  const csrfToken = Math.random().toString(36).slice(2)

  return (
    <>
      <Navbar session={session} currentPage="contagem" />
      <main className="container">
        {message && (
          <div className={`mensagem ${msgType}`} data-auto-hide>
            <i className={`fas ${msgType === 'sucesso' ? 'fa-check-circle' : 'fa-exclamation-circle'}`}></i>{' '}
            {message}
          </div>
        )}

        {/* Formulário de Contagem */}
        <div className="form-container">
          <h2 className="form-title">
            <i className="fas fa-clipboard-check" style={{ color: 'var(--secondary)' }}></i>{' '}
            Registrar Contagem
            <span style={{ fontSize: '13px', color: 'var(--gray)', fontWeight: 400, marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: '10px' }}>
              <span>Inventário: <strong>{inventarioAtivo.codigo}</strong></span>
              <button type="button" id="btnScanQR" className="btn btn-sm btn-secondary" title="Ler QR Code com a câmera">
                <i className="fas fa-qrcode"></i> Scan QR
              </button>
            </span>
          </h2>

          <form id="formContagem">
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }} className="form-grid-2">
              {/* Depósito */}
              <div className="form-group">
                <label htmlFor="depositoInput"><i className="fas fa-warehouse"></i> Depósito</label>
                <div className="autocomplete-container">
                  <input type="text" id="depositoInput" name="deposito" required
                    placeholder="Digite ou selecione o depósito" autoComplete="off" />
                  <div id="depositoDropdown" className="autocomplete-dropdown" style={{ display: 'none' }}></div>
                </div>
                <div id="novoDepositoDiv" style={{ display: 'none', marginTop: '8px' }}>
                  <input type="text" name="nova_localizacao" placeholder="Localização (opcional)" />
                </div>
              </div>

              {/* Part Number */}
              <div className="form-group">
                <label htmlFor="partnumberInput"><i className="fas fa-barcode"></i> Part Number</label>
                <div className="autocomplete-container">
                  <input type="text" id="partnumberInput" name="partnumber" required
                    placeholder="Digite ou selecione o part number" autoComplete="off" />
                  <div id="pnDropdown" className="autocomplete-dropdown" style={{ display: 'none' }}></div>
                </div>
                <div id="erroPartNumberEncerrado" className="mensagem erro" style={{ display: 'none', marginTop: '8px', padding: '8px 12px' }}>
                  <i className="fas fa-ban"></i> Este partnumber já foi <strong>encerrado</strong>!
                </div>
                <div id="avisoStatusContagem" style={{ display: 'none', marginTop: '8px', padding: '8px 12px', borderRadius: '8px', fontSize: '13px', fontWeight: 600, borderLeft: '4px solid var(--info)', background: '#eff6ff', color: '#1e40af' }}></div>
                <div id="novoPnDiv" style={{ display: 'none', marginTop: '8px' }}>
                  <input type="text" name="nova_descricao" placeholder="Descrição (opcional)" />
                  <input type="text" name="nova_unidade" placeholder="Unidade (ex: UN, CX)" style={{ marginTop: '8px' }} />
                </div>
              </div>

              {/* Quantidade */}
              <div className="form-group">
                <label htmlFor="quantidade"><i className="fas fa-sort-numeric-up"></i> Quantidade</label>
                <input type="number" id="quantidade" name="quantidade" required
                  min="0.0001" step="0.0001" placeholder="0" />
              </div>

              {/* Observações */}
              <div className="form-group">
                <label htmlFor="observacoes">
                  <i className="fas fa-sticky-note"></i> Observações{' '}
                  <span style={{ fontWeight: 400, color: 'var(--gray)', textTransform: 'none' }}>(opcional)</span>
                </label>
                <input type="text" id="observacoes" name="observacoes"
                  placeholder="Observações sobre esta contagem" />
              </div>
            </div>

            <button type="submit" className="btn btn-primary" style={{ marginTop: '8px' }} id="btnRegistrar">
              <i className="fas fa-plus-circle"></i>
              <span className="btn-text"> Registrar Contagem</span>
            </button>
          </form>
        </div>

        {/* Admin: Cards + Filtros + Tabela */}
        {isAdm && (
          <>
            <div className="cards-container">
              <div className="card">
                <h3>Total</h3>
                <div className="value">{stats.total}</div>
              </div>
              <div className="card" style={{ borderTopColor: 'var(--success)' }}>
                <h3 style={{ color: 'var(--success)' }}>Concluídas</h3>
                <div className="value" style={{ color: 'var(--success)' }}>{stats.concluidas}</div>
              </div>
              <div className="card" style={{ borderTopColor: 'var(--danger)' }}>
                <h3 style={{ color: 'var(--danger)' }}>Divergentes</h3>
                <div className="value" style={{ color: 'var(--danger)' }}>{stats.divergentes}</div>
              </div>
              <div className="card" style={{ borderTopColor: 'var(--warning)' }}>
                <h3 style={{ color: 'var(--warning)' }}>Pendentes</h3>
                <div className="value" style={{ color: 'var(--warning)' }}>{stats.pendentes}</div>
              </div>
            </div>

            {/* Filtros */}
            <div className="form-container" style={{ padding: '16px' }}>
              <form method="GET" action="/contagem" style={{ display: 'flex', gap: '12px', flexWrap: 'wrap', alignItems: 'flex-end' }}>
                <div style={{ flex: 1, minWidth: '200px' }}>
                  <label style={{ fontSize: '11px', marginBottom: '4px', display: 'block', textTransform: 'uppercase', letterSpacing: '.4px' }}>Part Number</label>
                  <input type="text" name="partnumber" defaultValue={filters.partnumber} placeholder="Filtrar..." />
                </div>
                <div style={{ flex: 1, minWidth: '200px' }}>
                  <label style={{ fontSize: '11px', marginBottom: '4px', display: 'block', textTransform: 'uppercase', letterSpacing: '.4px' }}>Depósito</label>
                  <input type="text" name="deposito" defaultValue={filters.deposito} placeholder="Filtrar..." />
                </div>
                <div style={{ display: 'flex', gap: '8px', alignItems: 'flex-end' }}>
                  <button type="submit" className="btn btn-primary btn-sm">
                    <i className="fas fa-filter"></i> Filtrar
                  </button>
                  <a href="/contagem" className="btn btn-ghost btn-sm">
                    <i className="fas fa-times"></i>
                  </a>
                </div>
              </form>
            </div>

            {/* Tabela */}
            <div className="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Depósito</th>
                    <th>Part Number</th>
                    <th>1ª Contagem</th>
                    <th>2ª Contagem</th>
                    <th>3ª Contagem</th>
                    <th>Qtd. Final</th>
                    <th>Status</th>
                    <th>Contador</th>
                    <th>Data</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {pagination.items.length === 0 ? (
                    <tr>
                      <td colSpan={10} style={{ textAlign: 'center', padding: '32px', color: 'var(--gray)' }}>
                        <i className="fas fa-inbox fa-2x" style={{ display: 'block', marginBottom: '8px', opacity: 0.4 }}></i>
                        Nenhuma contagem encontrada.
                      </td>
                    </tr>
                  ) : (
                    pagination.items.map((c) => {
                      const finalizado = !!c.finalizado
                      const numContagens = c.numero_contagens_realizadas
                      const podeNova = !!c.pode_nova_contagem
                      const qtd1 = c.quantidade_primaria !== null ? Number(c.quantidade_primaria) : null
                      const qtd2 = c.quantidade_secundaria !== null ? Number(c.quantidade_secundaria) : null
                      const qtd3 = c.quantidade_terceira !== null ? Number(c.quantidade_terceira) : null
                      const qtdFinal = c.quantidade_final !== null ? Number(c.quantidade_final) : null
                      const trClass = getRowClass(c)
                      const badge = getBadge(c)

                      return (
                        <tr key={c.id} className={trClass} data-contagem-id={c.id}>
                          <td><strong>{c.deposito}</strong></td>
                          <td>
                            <strong>{c.partnumber}</strong>
                            {podeNova && !finalizado && numContagens === 1 && (
                              <><br /><small style={{ color: '#1e40af', background: '#dbeafe', padding: '2px 7px', borderRadius: '4px', fontSize: '10px', fontWeight: 700, display: 'inline-block', marginTop: '3px' }}>
                                <i className="fas fa-arrow-right"></i> Liberada 2ª contagem
                              </small></>
                            )}
                            {podeNova && !finalizado && numContagens === 2 && (
                              <><br /><small style={{ color: '#1e40af', background: '#dbeafe', padding: '2px 7px', borderRadius: '4px', fontSize: '10px', fontWeight: 700, display: 'inline-block', marginTop: '3px' }}>
                                <i className="fas fa-arrow-right"></i> Liberada 3ª contagem
                              </small></>
                            )}
                            {c.lote && (
                              <><br /><small style={{ color: 'var(--gray)', fontSize: '11px' }}>
                                <i className="fas fa-tag"></i> {c.lote}
                              </small></>
                            )}
                          </td>
                          <td className="qtd-cell" dangerouslySetInnerHTML={{ __html: fmtQtd(qtd1) }}></td>
                          <td className="qtd-cell" dangerouslySetInnerHTML={{ __html: fmtQtd(qtd2, qtd1) }}></td>
                          <td className="qtd-cell" dangerouslySetInnerHTML={{ __html: fmtQtd(qtd3, qtd2 ?? qtd1) }}></td>
                          <td>
                            {qtdFinal !== null ? (
                              <strong style={{ color: 'var(--success)', fontSize: '15px' }}>
                                {qtdFinal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                              </strong>
                            ) : (
                              <span style={{ color: '#cbd5e1' }}>—</span>
                            )}
                          </td>
                          <td dangerouslySetInnerHTML={{ __html: badge }}></td>
                          <td style={{ fontSize: '13px' }}>{(c as any).usuario_nome || '—'}</td>
                          <td style={{ fontSize: '12px', whiteSpace: 'nowrap', color: 'var(--gray)' }}>
                            {c.data_contagem_primaria
                              ? new Date(c.data_contagem_primaria).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
                              : '—'}
                          </td>
                          <td>
                            {finalizado ? (
                              <span style={{ color: 'var(--gray)', fontSize: '12px' }}><i className="fas fa-lock"></i></span>
                            ) : (
                              <button
                                type="button"
                                className="btn-acao"
                                id={`btnAcao_${c.id}`}
                                data-contagem-id={c.id}
                                data-partnumber={c.partnumber}
                                data-deposito={c.deposito}
                                data-inventario-id={inventarioAtivo.id}
                                data-num-contagens={numContagens}
                                data-is-admin={isAdm ? 'true' : 'false'}
                              >
                                <i className="fas fa-ellipsis-h"></i> Ação
                              </button>
                            )}
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            </div>

            {/* Paginação */}
            {pagination.total_pages > 1 && (
              <div className="pagination">
                {pagination.page > 1 && (
                  <a href={`${paginaBase}${filterQuery ? '&' : '?'}p=${pagination.page - 1}`}>
                    <i className="fas fa-chevron-left"></i>
                  </a>
                )}
                {Array.from({ length: pagination.total_pages }, (_, i) => i + 1)
                  .filter(i => Math.abs(i - pagination.page) <= 2)
                  .map(i => (
                    i === pagination.page
                      ? <span key={i} className="current">{i}</span>
                      : <a key={i} href={`${paginaBase}${filterQuery ? '&' : '?'}p=${i}`}>{i}</a>
                  ))}
                {pagination.page < pagination.total_pages && (
                  <a href={`${paginaBase}${filterQuery ? '&' : '?'}p=${pagination.page + 1}`}>
                    <i className="fas fa-chevron-right"></i>
                  </a>
                )}
              </div>
            )}
          </>
        )}

        {/* Modal de Ações (para admin) */}
        {isAdm && (
          <div id="acaoModal" className="modal-overlay" style={{ display: 'none' }}>
            <div className="modal">
              <div className="modal-header">
                <h3 id="acaoModalTitle"><i className="fas fa-cog"></i> Ações da Contagem</h3>
              </div>
              <div className="modal-content" id="acaoModalContent"></div>
              <div className="modal-actions">
                <button id="btnFecharAcaoModal" className="btn btn-outline">
                  <i className="fas fa-times"></i> Fechar
                </button>
              </div>
            </div>
          </div>
        )}

        {/* QR Scanner Modal */}
        <div id="qrScannerModal" className="modal-overlay" style={{ display: 'none' }}>
          <div className="modal" style={{ maxWidth: '600px' }}>
            <div className="modal-header">
              <h3><i className="fas fa-qrcode"></i> Scanner de QR Code</h3>
            </div>
            <div className="modal-content">
              <p style={{ color: 'var(--gray)', marginBottom: '15px', textAlign: 'center' }}>
                Posicione o QR Code dentro da área marcada
              </p>
              <div id="qr-reader" style={{ width: '100%' }}></div>
              <div style={{ marginTop: '15px', padding: '12px', background: '#f0f8ff', borderRadius: 'var(--border-r)', fontSize: '13px', color: 'var(--secondary)' }}>
                <strong><i className="fas fa-info-circle"></i> Formato esperado:</strong><br />
                <code style={{ background: 'white', padding: '4px 8px', borderRadius: '4px', marginTop: '6px', display: 'inline-block' }}>DEP + PARTNUMBER</code>
                <br /><small style={{ color: 'var(--gray)', marginTop: '4px', display: 'block' }}>Exemplo: B9M555119496R → Depósito: <strong>B9M</strong> | Part Number: <strong>555119496R</strong></small>
              </div>
            </div>
            <div className="modal-actions">
              <button id="btnFecharQR" className="btn btn-outline">
                <i className="fas fa-times"></i> Cancelar
              </button>
            </div>
          </div>
        </div>

        <script dangerouslySetInnerHTML={{
          __html: `
          window.csrfToken = ${JSON.stringify(csrfToken)};
          window.inventarioId = ${inventarioAtivo.id};
          window.isAdmin = ${isAdm};

          // Event delegation global — funciona sem esperar DOMContentLoaded
          document.addEventListener('click', function(e) {
            var t = e.target;

            // Botão de ação na linha da tabela (ou ícone dentro dele)
            var btnAcao = t.closest ? t.closest('.btn-acao') : null;
            if (btnAcao) {
              e.preventDefault();
              e.stopPropagation();
              abrirAcaoModal(
                parseInt(btnAcao.dataset.contagemId),
                btnAcao.dataset.partnumber,
                btnAcao.dataset.deposito,
                parseInt(btnAcao.dataset.inventarioId),
                parseInt(btnAcao.dataset.numContagens),
                btnAcao.dataset.isAdmin === 'true'
              );
              return;
            }

            // Fechar modal pelo botão
            if (t.closest && t.closest('#btnFecharAcaoModal')) {
              fecharAcaoModal();
              return;
            }

            // Fechar modal ao clicar no overlay (fora do .modal)
            var acaoModal = document.getElementById('acaoModal');
            if (acaoModal && acaoModal.style.display !== 'none' && t === acaoModal) {
              fecharAcaoModal();
              return;
            }

            // QR
            if (t.closest && t.closest('#btnScanQR')) { iniciarScannerQR(); return; }
            if (t.closest && t.closest('#btnFecharQR')) { fecharScannerQR(); return; }
          });

          // Formulário de contagem via delegation (evita bind em DOMContentLoaded)
          document.addEventListener('submit', async function(e) {
            if (!e.target.id || e.target.id !== 'formContagem') return;
            e.preventDefault();
            var btn = document.getElementById('btnRegistrar');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...'; }
            var fd = new FormData(e.target);
            fd.append('acao_contagem', 'registrar');
            try {
              var resp = await fetch('/api/contagem', { method: 'POST', body: fd });
              var data = await resp.json();
              var msgType = data.success ? 'sucesso' : 'erro';
              window.location.href = '/contagem?msg=' + encodeURIComponent(data.message || data.error || '') + '&msgType=' + msgType;
            } catch(ex) {
              if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus-circle"></i> <span class="btn-text">Registrar Contagem</span>'; }
            }
          });
        `}} />
      </main>
      <Footer />
    </>
  )
}