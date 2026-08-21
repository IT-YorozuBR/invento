import { getIronSession, IronSession, SessionOptions } from 'iron-session'
import { cookies } from 'next/headers'

export interface SessionData {
  usuarioId?:       number
  usuarioNome?:     string
  usuarioMatricula?: string
  usuarioTipo?:     'admin' | 'operador'
  loginTime?:       number
  lastActivity?:    number
}

// Cookie "secure" exige HTTPS para ser aceito pelo navegador. Em produção
// atrás de um proxy sem TLS (ex.: domínio .local interno), isso faz o login
// "funcionar" no servidor mas o cookie nunca ser salvo no navegador.
// SESSION_COOKIE_SECURE permite desligar explicitamente nesses casos.
const cookieSecure =
  process.env.SESSION_COOKIE_SECURE !== undefined
    ? process.env.SESSION_COOKIE_SECURE === 'true'
    : process.env.NODE_ENV === 'production'

export const sessionOptions: SessionOptions = {
  password:    process.env.SESSION_SECRET || 'default-dev-secret-change-in-production',
  cookieName:  'invento_session',
  cookieOptions: {
    secure:   cookieSecure,
    httpOnly: true,
    sameSite: 'strict',
  },
}

export async function getSession(): Promise<IronSession<SessionData>> {
  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)
  return session
}

// Usada em API routes (com req/res do Next.js)
export async function getSessionFromRequest(
  req: Request,
  res: Response
): Promise<IronSession<SessionData>> {
  // @ts-ignore
  const session = await getIronSession<SessionData>(req, res, sessionOptions)
  return session
}

export function isAuthenticated(session: SessionData): boolean {
  return !!session.usuarioId
}

export function isAdmin(session: SessionData): boolean {
  return session.usuarioTipo === 'admin'
}

export function checkSessionTimeout(session: SessionData): boolean {
  const timeout = parseInt(process.env.SESSION_TIMEOUT || '3600')
  if (!session.lastActivity) return true
  return (Date.now() / 1000 - session.lastActivity) <= timeout
}
