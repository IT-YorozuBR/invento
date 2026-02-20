import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { cookies } from 'next/headers'
import { sessionOptions, SessionData } from '@/lib/session'
import {
  inventarioFindAtivo,
  contagemRegistrarPrimaria,
  contagemFindOpenByPartnumber,
  depositoSave,
  depositoTouch,
  partnumberSave,
  partnumberTouch,
  notificacaoCriar,
} from '@/lib/models'

export async function POST(req: NextRequest) {
  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

  if (!session.usuarioId) {
    return NextResponse.json({ error: 'Não autenticado.' }, { status: 401 })
  }

  const inventarioAtivo = await inventarioFindAtivo()
  if (!inventarioAtivo) {
    return NextResponse.json({ error: 'Não há inventário ativo.' }, { status: 400 })
  }

  const formData = await req.formData()
  const acao = String(formData.get('acao_contagem') || '')

  if (acao !== 'registrar') {
    return NextResponse.json({ error: 'Ação desconhecida.' }, { status: 400 })
  }

  let deposito   = String(formData.get('deposito')   || '').toUpperCase().trim()
  let partnumber = String(formData.get('partnumber') || '').toUpperCase().trim()
  const quantidade = parseFloat(String(formData.get('quantidade') || '0'))

  if (!deposito || !partnumber) {
    return NextResponse.json({ error: 'Depósito e Part Number são obrigatórios.' }, { status: 400 })
  }
  if (quantidade <= 0) {
    return NextResponse.json({ error: 'Quantidade deve ser maior que zero.' }, { status: 400 })
  }

  // Suporte a "OUTRO" (novo depósito)
  if (deposito === 'OUTRO') {
    const novaLocalizacao = String(formData.get('nova_localizacao') || '').trim().toUpperCase()
    if (novaLocalizacao) {
      await depositoSave(novaLocalizacao, novaLocalizacao)
      deposito = novaLocalizacao
    }
  }

  // Suporte a "OUTRO" (novo partnumber)
  if (partnumber === 'OUTRO') {
    const novaDescricao = String(formData.get('nova_descricao') || '').trim().toUpperCase()
    if (novaDescricao) {
      await partnumberSave(
        novaDescricao,
        String(formData.get('nova_descricao') || ''),
        String(formData.get('nova_unidade') || 'UN')
      )
      partnumber = novaDescricao
    }
  }

  // Touch
  await depositoTouch(deposito)
  await partnumberTouch(partnumber, String(formData.get('descricao') || ''))

  const extra = {
    descricao: String(formData.get('descricao') || ''),
    unidade:   String(formData.get('unidade')   || 'UN'),
    lote:      String(formData.get('lote')      || '') || null,
    validade:  String(formData.get('validade')  || '') || null,
  }

  const result = await contagemRegistrarPrimaria(
    inventarioAtivo.id,
    session.usuarioId,
    deposito,
    partnumber,
    quantidade,
    extra
  )

  // Notificar admin se não for admin
  if (result.success && session.usuarioTipo !== 'admin') {
    try {
      const contagemAtual = await contagemFindOpenByPartnumber(inventarioAtivo.id, partnumber, deposito)
      const fase = contagemAtual ? contagemAtual.numero_contagens_realizadas : 1
      await notificacaoCriar(inventarioAtivo.id, session.usuarioNome || '', partnumber, deposito, fase)
    } catch (e) {
      // notificação é não-crítica
    }
  }

  return NextResponse.json(result)
}
