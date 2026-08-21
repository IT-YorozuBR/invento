import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { cookies } from 'next/headers'
import { sessionOptions, SessionData } from '@/lib/session'

export async function GET(req: NextRequest) {
  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)
  session.destroy()

  // Em modo standalone (Docker), req.url reflete o host interno do
  // container (ex.: 0.0.0.0:3000) em vez do host que o navegador
  // usou — atrás de um proxy reverso isso gera um redirect
  // inalcançável. Usa o header Host (ou X-Forwarded-Host, setado por
  // proxies como o Nginx Proxy Manager) para montar a URL real.
  const host = req.headers.get('x-forwarded-host') || req.headers.get('host') || req.nextUrl.host
  const proto = req.headers.get('x-forwarded-proto') || req.nextUrl.protocol.replace(':', '')

  return NextResponse.redirect(`${proto}://${host}/login`)
}
