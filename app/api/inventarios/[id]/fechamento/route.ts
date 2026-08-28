import { NextRequest, NextResponse } from 'next/server'
import { requireAdmin, handle, jsonError, parseId } from '@/lib/api'
import { inventarioFechar } from '@/lib/models'

// POST /api/inventarios/:id/fechamento  — encerra um inventário (admin)
export async function POST(
  _req: NextRequest,
  { params }: { params: { id: string } }
) {
  return handle('inventarios.fechamento.POST', async () => {
    const { error } = await requireAdmin()
    if (error) return error

    const id = parseId(params.id)
    if (!id) return jsonError('ID inválido.', 400)

    const result = await inventarioFechar(id)
    return NextResponse.json(result, { status: result.success ? 200 : 400 })
  })
}
