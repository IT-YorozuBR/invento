import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { cookies } from 'next/headers'
import { sessionOptions, SessionData } from '@/lib/session'
import { usuarioFindOrCreate, inventarioFindAtivo } from '@/lib/models'
import { initDatabase } from '@/lib/init'

export async function POST(req: NextRequest) {
  await initDatabase()
  const formData = await req.formData()
  const nome      = String(formData.get('nome') || '').trim()
  const matricula = String(formData.get('matricula') || '').trim()
  const senha     = String(formData.get('senha') || '')

  if (!nome || !matricula) {
    return NextResponse.json({ error: 'Preencha todos os campos obrigatórios.' }, { status: 400 })
  }

  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

  const adminPwd = process.env.ADMIN_PASSWORD || ''
  let userId: number | null = null
  let tipo: 'admin' | 'operador' = 'operador'

  if (matricula === 'admin') {
    if (!senha || senha !== adminPwd) {
      return NextResponse.json({ error: 'Credenciais administrativas inválidas.' }, { status: 401 })
    }
    userId = await usuarioFindOrCreate(nome, 'admin', 'admin')
    tipo = 'admin'
  } else {
    userId = await usuarioFindOrCreate(nome, matricula, 'operador')
    tipo = 'operador'
  }

  if (!userId) {
    return NextResponse.json({ error: 'Erro ao processar login. Tente novamente.' }, { status: 500 })
  }

  session.usuarioId        = userId
  session.usuarioNome      = nome
  session.usuarioMatricula = matricula
  session.usuarioTipo      = tipo
  session.loginTime        = Math.floor(Date.now() / 1000)
  session.lastActivity     = Math.floor(Date.now() / 1000)
  await session.save()

  // Redirecionar para URL correta
  let redirectTo = '/dashboard'
  if (tipo !== 'admin') {
    const inventario = await inventarioFindAtivo()
    redirectTo = inventario ? '/contagem' : '/dashboard'
  }

  return NextResponse.json({ success: true, redirectTo })
}
