import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { cookies } from 'next/headers'
import { sessionOptions, SessionData, isAdmin } from '@/lib/session'
import { depositoSave, depositoDelete, partnumberSave, partnumberDelete } from '@/lib/models'

export async function POST(req: NextRequest) {
  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

  if (!session.usuarioId) {
    return NextResponse.json({ error: 'Não autenticado.' }, { status: 401 })
  }
  if (!isAdmin(session)) {
    return NextResponse.json({ error: 'Apenas administradores.' }, { status: 403 })
  }

  const formData = await req.formData()
  const acao = String(formData.get('acao') || '')

  if (acao === 'cadastrar_deposito') {
    const deposito    = String(formData.get('deposito') || '').trim().toUpperCase()
    const localizacao = String(formData.get('localizacao') || '').trim()

    if (!deposito) return NextResponse.json({ error: 'Nome do depósito é obrigatório.' }, { status: 400 })

    const result = await depositoSave(deposito, localizacao)
    return NextResponse.json(result)
  }

  if (acao === 'cadastrar_partnumber') {
    const pn       = String(formData.get('partnumber') || '').trim().toUpperCase()
    const descricao = String(formData.get('descricao') || '').trim()
    const unidade   = String(formData.get('unidade')   || 'UN').trim()

    if (!pn) return NextResponse.json({ error: 'Part Number é obrigatório.' }, { status: 400 })

    const result = await partnumberSave(pn, descricao, unidade)
    return NextResponse.json(result)
  }

  if (acao === 'excluir_deposito') {
    const deposito = String(formData.get('deposito') || '').trim()
    if (!deposito) return NextResponse.json({ error: 'Nome do depósito inválido.' }, { status: 400 })
    const result = await depositoDelete(deposito)
    return NextResponse.json(result)
  }

  if (acao === 'excluir_partnumber') {
    const pn = String(formData.get('partnumber') || '').trim()
    if (!pn) return NextResponse.json({ error: 'Nome do part number inválido.' }, { status: 400 })
    const result = await partnumberDelete(pn)
    return NextResponse.json(result)
  }

  // Importar CSV de partnumbers
  if (acao === 'importar_partnumbers') {
    const file = formData.get('arquivo_csv')
    if (!file || typeof file === 'string') {
      return NextResponse.json({ error: 'Arquivo inválido.' }, { status: 400 })
    }
    const text = await (file as File).text()
    const lines = text.split('\n')
    let sucessos = 0, erros = 0
    const mensagens: string[] = []

    for (let i = 1; i < lines.length; i++) {
      const line = lines[i].trim()
      if (!line) continue
      const cols = line.split(';')
      const pn   = (cols[0] || '').trim()
      if (!pn) { erros++; continue }
      const result = await partnumberSave(pn, (cols[1] || '').trim(), (cols[2] || 'UN').trim())
      if (result.success) {
        sucessos++
      } else {
        erros++
        mensagens.push(`Linha ${i + 1}: ${result.message}`)
      }
    }
    return NextResponse.json({ success: true, message: `Importados: ${sucessos} | Erros: ${erros}`, sucessos, erros, mensagens })
  }

  return NextResponse.json({ error: 'Ação desconhecida.' }, { status: 400 })
}
