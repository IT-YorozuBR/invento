import { SessionData } from '@/lib/session'

interface NavbarProps {
  session: SessionData
  currentPage: string
}

export default function Navbar({ session, currentPage }: NavbarProps) {
  const isAdmin = session.usuarioTipo === 'admin'

  return (
    <>
      <nav className="navbar">
        <div className="navbar-brand">
          <i className="fa-solid fa-warehouse"></i>
          <span>Invento</span>
        </div>
        <div className="navbar-menu">
          {isAdmin ? (
            <>
              <a href="/dashboard" className={`nav-link ${currentPage === 'dashboard' ? 'active' : ''}`}>
                <i className="fas fa-tachometer-alt"></i> Dashboard
              </a>
              <a href="/contagem" className={`nav-link ${currentPage === 'contagem' ? 'active' : ''}`}>
                <i className="fas fa-clipboard-check"></i> Contagem
              </a>
              <a href="/cadastros?tipo=depositos" className={`nav-link ${currentPage === 'cadastros' ? 'active' : ''}`}>
                <i className="fas fa-database"></i> Cadastros
              </a>
              <a href="/inventarios-concluidos" className={`nav-link ${currentPage === 'inventarios-concluidos' ? 'active' : ''}`}>
                <i className="fas fa-history"></i> Concluídos
              </a>
            </>
          ) : (
            <a href="/contagem" className={`nav-link ${currentPage === 'contagem' ? 'active' : ''}`}>
              <i className="fas fa-clipboard-check"></i> Contagem
            </a>
          )}

          <div className="user-info">
            {isAdmin && (
              <button
                id="btnNotificacoes"
                className="notif-bell"
                title="Notificações de atividade"
                onClick={undefined}
              >
                <i className="fas fa-bell"></i>
                <span id="notifBadge" className="notif-badge" style={{ display: 'none' }}>0</span>
              </button>
            )}

            <span style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '14px' }}>
              <i className="fas fa-user-circle"></i>
              {session.usuarioNome}
              <span className={`badge badge-${isAdmin ? 'warning' : 'info'}`}>
                {isAdmin ? 'Admin' : 'Operador'}
              </span>
            </span>
            <a href="/api/auth/logout" className="btn btn-sm btn-outline">
              <i className="fas fa-sign-out-alt"></i> Sair
            </a>
          </div>
        </div>
      </nav>

      {isAdmin && (
        <div id="painelNotificacoes" className="notif-painel" style={{ display: 'none' }}>
          <div className="notif-painel-header">
            <span><i className="fas fa-bell"></i> Atividade dos Operadores</span>
            <button className="notif-fechar">&times;</button>
          </div>
          <div id="notifLista" className="notif-lista">
            <p className="notif-vazio"><i className="fas fa-check-circle"></i> Nenhuma atividade recente.</p>
          </div>
        </div>
      )}
    </>
  )
}
