import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { cookies } from 'next/headers'
import { sessionOptions, SessionData, isAdmin } from '@/lib/session'
import { inventarioCreate, inventarioFechar } from '@/lib/models'

// export async function POST(req: NextRequest) {
//   const cookieStore = cookies()
//   const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

//   if (!session.usuarioId) {
//     return NextResponse.json({ error: 'Não autenticado.' }, { status: 401 })
//   }
//   if (!isAdmin(session)) {
//     return NextResponse.json({ error: 'Apenas administradores.' }, { status: 403 })
//   }

//   const formData = await req.formData()
//   const acao = String(formData.get('acao_inventario') || '')

//   if (acao === 'criar') {
//     const dataInicio = String(formData.get('data_inicio') || '')
//     const descricao  = String(formData.get('descricao') || '').trim()

//     if (!dataInicio || !/^\d{4}-\d{2}-\d{2}$/.test(dataInicio)) {
//       return NextResponse.json({ error: 'Data inválida.' }, { status: 400 })
//     }

//     const result = await inventarioCreate(dataInicio, descricao, session.usuarioId)
//     return NextResponse.json(result)
//   }

//   if (acao === 'fechar') {
//     const id = parseInt(String(formData.get('inventario_id') || '0'))
//     if (id <= 0) return NextResponse.json({ error: 'ID inválido.' }, { status: 400 })
//     const result = await inventarioFechar(id)
//     return NextResponse.json(result)
//   }

//   return NextResponse.json({ error: 'Ação desconhecida.' }, { status: 400 })
// }


export async function POST(req: NextRequest) {
  try {
    const cookieStore = cookies()
    const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

    if (!session.usuarioId) {
      return NextResponse.json({ error: 'Não autenticado.' }, { status: 401 })
    }
    if (!isAdmin(session)) {
      return NextResponse.json({ error: 'Apenas administradores.' }, { status: 403 })
    }

    const formData = await req.formData()
    const acao = String(formData.get('acao_inventario') || '')

    if (acao === 'criar') {
      const dataInicio = String(formData.get('data_inicio') || '')
      const descricao = String(formData.get('descricao') || '').trim()

      if (!dataInicio || !/^\d{4}-\d{2}-\d{2}$/.test(dataInicio)) {
        return NextResponse.json({ error: 'Data inválida.' }, { status: 400 })
      }

      const result = await inventarioCreate(dataInicio, descricao, session.usuarioId)
      return NextResponse.json(result)
    }

    if (acao === 'fechar') {
      const id = parseInt(String(formData.get('inventario_id') || '0'))
      if (id <= 0) return NextResponse.json({ error: 'ID inválido.' }, { status: 400 })
      const result = await inventarioFechar(id)
      return NextResponse.json(result)
    }

    return NextResponse.json({ error: 'Ação desconhecida.' }, { status: 400 })

  } catch (err: any) {
    console.error('[inventario route] Erro:', err)
    return NextResponse.json({ error: err?.message || 'Erro interno do servidor.' }, { status: 500 })
  }
}