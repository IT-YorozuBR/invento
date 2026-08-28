import { NextRequest, NextResponse } from 'next/server'
import { requireAdmin, handle, parseId } from '@/lib/api'
import {
  contagemIniciarSegundaContagem,
  contagemIniciarTerceiraContagem,
  contagemFindById,
} from '@/lib/models'

// POST /api/contagens/:id/liberacoes  body: { fase: 2 | 3 }
// Libera a próxima contagem do item (admin).
export async function POST(
  req: NextRequest,
  { params }: { params: { id: string } }
) {
  return handle('contagens.liberacoes.POST', async () => {
    const { error } = await requireAdmin()
    if (error) {
      // mantém o contrato { success, message } usado pelo front
      return NextResponse.json(
        { success: false, message: 'Apenas administradores podem liberar contagens.' },
        { status: error.status }
      )
    }

    const contagemId = parseId(params.id)
    if (!contagemId) {
      return NextResponse.json({ success: false, message: 'ID de contagem inválido' }, { status: 400 })
    }

    const body = await req.json().catch(() => ({}))
    const fase = Number(body?.fase)
    if (fase !== 2 && fase !== 3) {
      return NextResponse.json({ success: false, message: 'Fase inválida (use 2 ou 3).' }, { status: 400 })
    }

    const result = fase === 2
      ? await contagemIniciarSegundaContagem(contagemId)
      : await contagemIniciarTerceiraContagem(contagemId)

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
          },
        })
      }
    }

    return NextResponse.json(result, { status: result.success ? 200 : 400 })
  })
}
