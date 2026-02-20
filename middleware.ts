import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { sessionOptions, SessionData } from './lib/session'

// Rotas que não precisam de autenticação
const PUBLIC_ROUTES = ['/login', '/api/auth/login']

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
    const loginUrl = new URL('/login', req.url)
    return NextResponse.redirect(loginUrl)
  }

  // Verifica timeout de sessão
  const timeout = parseInt(process.env.SESSION_TIMEOUT || '3600')
  if (session.lastActivity && (Date.now() / 1000 - session.lastActivity) > timeout) {
    session.destroy()
    const loginUrl = new URL('/login?timeout=1', req.url)
    return NextResponse.redirect(loginUrl)
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
