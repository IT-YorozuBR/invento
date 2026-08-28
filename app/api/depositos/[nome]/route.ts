import { NextRequest, NextResponse } from 'next/server'
import { requireAdmin, handle, jsonError } from '@/lib/api'
import { depositoDelete } from '@/lib/models'

// DELETE /api/depositos/:nome  — exclui um depósito (admin)
export async function DELETE(
  _req: NextRequest,
  { params }: { params: { nome: string } }
) {
  return handle('depositos.DELETE', async () => {
    const { error } = await requireAdmin()
    if (error) return error

    const deposito = decodeURIComponent(params.nome || '').trim()
    if (!deposito) return jsonError('Nome do depósito inválido.', 400)

    const result = await depositoDelete(deposito)
    return NextResponse.json(result, { status: result.success ? 200 : 400 })
  })
}
