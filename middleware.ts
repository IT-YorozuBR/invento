import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { sessionOptions, SessionData } from './lib/session'

// Rotas que não precisam de autenticação
const PUBLIC_ROUTES = ['/login', '/api/auth/login']

// Atrás de proxy reverso (modo standalone), req.url reflete o host interno
// do container. Monta a URL com o host/protocolo que o navegador usou.
function publicUrl(req: NextRequest, path: string) {
  const host = req.headers.get('x-forwarded-host') || req.headers.get('host') || req.nextUrl.host
  const proto = req.headers.get('x-forwarded-proto') || req.nextUrl.protocol.replace(':', '')
  return new URL(path, `${proto}://${host}`)
}

export async function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl

  // Deixar rotas públicas e assets passarem
  if (
    PUBLIC_ROUTES.some(r => pathname.startsWith(r)) ||
    pathname.startsWith('/_next') ||
    pathname.startsWith('/assets') ||
    pathname === '/favicon.ico'
  ) {
    return NextResponse.next()
  }

  // Verifica sessão
  const res = NextResponse.next()
  
  // @ts-ignore
  const session = await getIronSession<SessionData>(req, res, sessionOptions)

  if (!session.usuarioId) {
    // Redireciona para login
    const loginUrl = publicUrl(req, '/login')
    return NextResponse.redirect(loginUrl)
  }

  // Verifica timeout de sessão
  const timeout = parseInt(process.env.SESSION_TIMEOUT || '3600')
  if (session.lastActivity && (Date.now() / 1000 - session.lastActivity) > timeout) {
    // O cookie precisa ser limpo na resposta de redirect (e não em `res`),
    // senão o cookie expirado continua no navegador e /login ↔ /dashboard
    // entram em loop de redirecionamento.
    const redirectRes = NextResponse.redirect(publicUrl(req, '/login?timeout=1'))
    // @ts-ignore
    const expired = await getIronSession<SessionData>(req, redirectRes, sessionOptions)
    expired.destroy()
    return redirectRes
  }

  // Atualiza lastActivity
  session.lastActivity = Math.floor(Date.now() / 1000)
  await session.save()

  return res
}

export const config = {
  matcher: [
    '/((?!_next/static|_next/image|favicon.ico|assets).*)',
  ],
}
