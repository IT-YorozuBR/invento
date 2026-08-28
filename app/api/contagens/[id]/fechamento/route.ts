import { NextRequest, NextResponse } from 'next/server'
import { requireAdmin, handle, parseId } from '@/lib/api'
import { contagemFinalizar } from '@/lib/models'

// POST /api/contagens/:id/fechamento  — encerra a contagem de um item (admin).
export async function POST(
  _req: NextRequest,
  { params }: { params: { id: string } }
) {
  return handle('contagens.fechamento.POST', async () => {
    const { error } = await requireAdmin()
    if (error) {
      return NextResponse.json(
        { success: false, message: 'Apenas administradores podem finalizar contagens.' },
        { status: error.status }
      )
    }

    const contagemId = parseId(params.id)
    if (!contagemId) {
      return NextResponse.json({ success: false, message: 'ID de contagem inválido' }, { status: 400 })
    }

    const result = await contagemFinalizar(contagemId)
    return NextResponse.json(result, { status: result.success ? 200 : 400 })
  })
}
