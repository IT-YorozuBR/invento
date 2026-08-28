import { NextRequest, NextResponse } from 'next/server'
import { requireAuth, handle, jsonError, readForm } from '@/lib/api'
import {
  inventarioFindAtivo,
  contagemFindOpenByPartnumber,
  contagemRegistrarPrimaria,
  depositoSave,
  depositoTouch,
  partnumberSave,
  partnumberTouch,
  notificacaoCriar,
} from '@/lib/models'

// GET /api/contagens?partnumber=&deposito=  — status da contagem aberta do item
export async function GET(req: NextRequest) {
  return handle('contagens.GET', async () => {
    const { error } = await requireAuth()
    if (error) return error

    const sp = new URL(req.url).searchParams
    const partnumber = (sp.get('partnumber') || '').trim().toUpperCase()
    const deposito = (sp.get('deposito') || '').trim().toUpperCase()

    if (!partnumber || !deposito) return NextResponse.json({ existe: false })

    const inventario = await inventarioFindAtivo()
    if (!inventario) return NextResponse.json({ existe: false })

    const contagem = await contagemFindOpenByPartnumber(inventario.id, partnumber, deposito)
    if (!contagem) return NextResponse.json({ existe: false })

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
  })
}

// POST /api/contagens  — registra a 1ª contagem de um item
export async function POST(req: NextRequest) {
  return handle('contagens.POST', async () => {
    const { session, error } = await requireAuth()
    if (error) return error

    const inventarioAtivo = await inventarioFindAtivo()
    if (!inventarioAtivo) return jsonError('Não há inventário ativo.', 400)

    const form = await readForm(req)
    if (form instanceof NextResponse) return form

    let deposito = String(form.get('deposito') || '').toUpperCase().trim()
    let partnumber = String(form.get('partnumber') || '').toUpperCase().trim()
    const quantidade = parseFloat(String(form.get('quantidade') || '0'))

    if (!deposito || !partnumber) {
      return jsonError('Depósito e Part Number são obrigatórios.', 400)
    }
    if (!Number.isFinite(quantidade) || quantidade <= 0) {
      return jsonError('Quantidade deve ser um número maior que zero.', 400)
    }

    // Suporte a "OUTRO" (novo depósito)
    if (deposito === 'OUTRO') {
      const novaLocalizacao = String(form.get('nova_localizacao') || '').trim().toUpperCase()
      if (novaLocalizacao) {
        await depositoSave(novaLocalizacao, novaLocalizacao)
        deposito = novaLocalizacao
      }
    }

    // Suporte a "OUTRO" (novo partnumber)
    if (partnumber === 'OUTRO') {
      const novaDescricao = String(form.get('nova_descricao') || '').trim().toUpperCase()
      if (novaDescricao) {
        await partnumberSave(
          novaDescricao,
          String(form.get('nova_descricao') || ''),
          String(form.get('nova_unidade') || 'UN')
        )
        partnumber = novaDescricao
      }
    }

    await depositoTouch(deposito)
    await partnumberTouch(partnumber, String(form.get('descricao') || ''))

    const extra = {
      descricao: String(form.get('descricao') || ''),
      unidade: String(form.get('unidade') || 'UN'),
      lote: String(form.get('lote') || '') || null,
      validade: String(form.get('validade') || '') || null,
    }

    const result = await contagemRegistrarPrimaria(
      inventarioAtivo.id,
      session.usuarioId!,
      deposito,
      partnumber,
      quantidade,
      extra
    )

    // Notifica o admin quando quem registrou não é admin
    if (result.success && session.usuarioTipo !== 'admin') {
      try {
        const contagemAtual = await contagemFindOpenByPartnumber(inventarioAtivo.id, partnumber, deposito)
        const fase = contagemAtual ? contagemAtual.numero_contagens_realizadas : 1
        await notificacaoCriar(inventarioAtivo.id, session.usuarioNome || '', partnumber, deposito, fase)
      } catch {
        // notificação é não-crítica
      }
    }

    return NextResponse.json(result, { status: result.success ? 201 : 400 })
  })
}
