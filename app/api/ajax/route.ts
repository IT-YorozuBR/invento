import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { cookies } from 'next/headers'
import { sessionOptions, SessionData, isAdmin } from '@/lib/session'
import {
  depositoSuggest,
  partnumberSuggest,
  inventarioFindAtivo,
  contagemVerificarFinalizado,
  contagemFindOpenByPartnumber,
  contagemIniciarSegundaContagem,
  contagemIniciarTerceiraContagem,
  contagemFinalizar,
  contagemFindById,
  notificacaoBuscarDesde,
} from '@/lib/models'

// GET - autocomplete e notificações
export async function GET(req: NextRequest) {
  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

  if (!session.usuarioId) {
    return NextResponse.json([], { status: 200 })
  }

  const { searchParams } = new URL(req.url)
  const acao = searchParams.get('acao') || ''
  const tipo = searchParams.get('tipo') || ''
  const termo = (searchParams.get('termo') || '').trim()

  // Notificações polling
  if (acao === 'notificacoes') {

    const desde = parseInt(searchParams.get('desde') || '0')
    const inventario = await inventarioFindAtivo()
    if (!inventario) {
      return NextResponse.json({ total: 0, items: [] })
    }
    const result = await notificacaoBuscarDesde(inventario.id, desde)
    return NextResponse.json(result)

  }

  // Autocomplete
  if (termo.length < 2) {
    return NextResponse.json([])
  }

  const result = tipo === 'partnumber'
    ? await partnumberSuggest(termo)
    : tipo === 'deposito'
      ? await depositoSuggest(termo)
      : []

  return NextResponse.json(result)
}

// POST - actions that require CSRF (liberar, finalizar, verificar)
export async function POST(req: NextRequest) {
  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

  if (!session.usuarioId) {
    return NextResponse.json({ error: 'Não autenticado' }, { status: 401 })
  }

  const { searchParams } = new URL(req.url)
  const acao = searchParams.get('acao') || ''

  const formData = await req.formData()

  if (acao === 'verificar_finalizado') {
    const partnumber = String(formData.get('partnumber') || '').trim().toUpperCase()
    const deposito = String(formData.get('deposito') || '').trim().toUpperCase()

    if (!partnumber || !deposito) {
      return NextResponse.json({ finalizado: false, existe: false })
    }

    const inventario = await inventarioFindAtivo()
    if (!inventario) {
      return NextResponse.json({ finalizado: false, existe: false })
    }

    const result = await contagemVerificarFinalizado(inventario.id, partnumber, deposito)
    return NextResponse.json(result)
  }

  if (acao === 'verificar_status_contagem') {
    const partnumber = String(formData.get('partnumber') || '').trim().toUpperCase()
    const deposito = String(formData.get('deposito') || '').trim().toUpperCase()

    if (!partnumber || !deposito) {
      return NextResponse.json({ existe: false })
    }

    const inventario = await inventarioFindAtivo()
    if (!inventario) {
      return NextResponse.json({ existe: false })
    }

    const contagem = await contagemFindOpenByPartnumber(inventario.id, partnumber, deposito)
    if (!contagem) {
      return NextResponse.json({ existe: false })
    }

    return NextResponse.json({
      existe: true,
      id: contagem.id,
      numero_contagens: contagem.numero_contagens_realizadas,
      pode_nova: !!contagem.pode_nova_contagem,
      finalizado: !!contagem.finalizado,
      status: contagem.status,
      quantidade_primaria: Number(contagem.quantidade_primaria),
      quantidade_secundaria: contagem.quantidade_secundaria !== null ? Number(contagem.quantidade_secundaria) : null,
      quantidade_terceira: contagem.quantidade_terceira !== null ? Number(contagem.quantidade_terceira) : null,
      quantidade_final: contagem.quantidade_final !== null ? Number(contagem.quantidade_final) : null,
    })
  }

  if (acao === 'liberar_segunda') {
    if (!isAdmin(session)) {
      return NextResponse.json({ success: false, message: 'Apenas administradores podem liberar contagens.' })
    }
    const contagemId = parseInt(String(formData.get('contagem_id') || '0'))
    if (contagemId <= 0) {
      return NextResponse.json({ success: false, message: 'ID de contagem inválido' })
    }

    const result = await contagemIniciarSegundaContagem(contagemId)
    if (result.success) {
      const updated = await contagemFindById(contagemId)
      if (updated) {
        return NextResponse.json({
          ...result,
          data: {
            id: updated.id,
            numero_contagens: updated.numero_contagens_realizadas,
            pode_nova_contagem: !!updated.pode_nova_contagem,
            status: updated.status,
            finalizado: !!updated.finalizado,
          }
        })
      }
    }
    return NextResponse.json(result)
  }

  if (acao === 'liberar_terceira') {
    if (!isAdmin(session)) {
      return NextResponse.json({ success: false, message: 'Apenas administradores podem liberar contagens.' })
    }
    const contagemId = parseInt(String(formData.get('contagem_id') || '0'))
    if (contagemId <= 0) {
      return NextResponse.json({ success: false, message: 'ID de contagem inválido' })
    }

    const result = await contagemIniciarTerceiraContagem(contagemId)
    if (result.success) {
      const updated = await contagemFindById(contagemId)
      if (updated) {
        return NextResponse.json({
          ...result,
          data: {
            id: updated.id,
            numero_contagens: updated.numero_contagens_realizadas,
            pode_nova_contagem: !!updated.pode_nova_contagem,
            status: updated.status,
            finalizado: !!updated.finalizado,
          }
        })
      }
    }
    return NextResponse.json(result)
  }

  if (acao === 'finalizar_contagem') {
    if (!isAdmin(session)) {
      return NextResponse.json({ success: false, message: 'Apenas administradores podem finalizar contagens.' })
    }
    const contagemId = parseInt(String(formData.get('contagem_id') || '0'))
    if (contagemId <= 0) {
      return NextResponse.json({ success: false, message: 'ID de contagem inválido' })
    }

    const result = await contagemFinalizar(contagemId)
    return NextResponse.json(result)
  }

  return NextResponse.json({ error: 'Ação desconhecida' }, { status: 400 })
}
