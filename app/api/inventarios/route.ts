import { NextRequest, NextResponse } from 'next/server'
import { requireAdmin, handle, jsonError, readForm } from '@/lib/api'
import { inventarioCreate } from '@/lib/models'

// POST /api/inventarios  — cria um novo inventário (admin)
export async function POST(req: NextRequest) {
  return handle('inventarios.POST', async () => {
    const { session, error } = await requireAdmin()
    if (error) return error

    const form = await readForm(req)
    if (form instanceof NextResponse) return form

    const dataInicio = String(form.get('data_inicio') || '')
    const descricao = String(form.get('descricao') || '').trim()

    if (!dataInicio || !/^\d{4}-\d{2}-\d{2}$/.test(dataInicio)) {
      return jsonError('Data inválida.', 400)
    }

    const result = await inventarioCreate(dataInicio, descricao, session.usuarioId!)
    return NextResponse.json(result, { status: result.success ? 201 : 400 })
  })
}
