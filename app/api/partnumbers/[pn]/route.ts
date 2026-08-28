import { NextRequest, NextResponse } from 'next/server'
import { requireAdmin, handle, jsonError } from '@/lib/api'
import { partnumberDelete } from '@/lib/models'

// DELETE /api/partnumbers/:pn  — exclui um part number (admin)
export async function DELETE(
  _req: NextRequest,
  { params }: { params: { pn: string } }
) {
  return handle('partnumbers.DELETE', async () => {
    const { error } = await requireAdmin()
    if (error) return error

    const pn = decodeURIComponent(params.pn || '').trim()
    if (!pn) return jsonError('Nome do part number inválido.', 400)

    const result = await partnumberDelete(pn)
    return NextResponse.json(result, { status: result.success ? 200 : 400 })
  })
}
