import { NextRequest, NextResponse } from 'next/server'
import { requireAuth, requireAdmin, handle, jsonError, readForm } from '@/lib/api'
import { partnumberSuggest, partnumberSave } from '@/lib/models'

// GET /api/partnumbers?q=<termo>  — autocomplete
export async function GET(req: NextRequest) {
  return handle('partnumbers.GET', async () => {
    const { error } = await requireAuth()
    if (error) return error

    const termo = (new URL(req.url).searchParams.get('q') || '').trim()
    if (termo.length < 2) return NextResponse.json([])

    return NextResponse.json(await partnumberSuggest(termo))
  })
}

// POST /api/partnumbers  — cadastra um part number (admin)
export async function POST(req: NextRequest) {
  return handle('partnumbers.POST', async () => {
    const { error } = await requireAdmin()
    if (error) return error

    const form = await readForm(req)
    if (form instanceof NextResponse) return form

    const pn = String(form.get('partnumber') || '').trim().toUpperCase()
    const descricao = String(form.get('descricao') || '').trim()
    const unidade = String(form.get('unidade') || 'UN').trim()

    if (!pn) return jsonError('Part Number é obrigatório.', 400)

    const result = await partnumberSave(pn, descricao, unidade)
    return NextResponse.json(result, { status: result.success ? 201 : 400 })
  })
}
