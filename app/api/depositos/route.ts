import { NextRequest, NextResponse } from 'next/server'
import { requireAuth, requireAdmin, handle, jsonError, readForm } from '@/lib/api'
import { depositoSuggest, depositoSave } from '@/lib/models'

// GET /api/depositos?q=<termo>  — autocomplete
export async function GET(req: NextRequest) {
  return handle('depositos.GET', async () => {
    const { error } = await requireAuth()
    if (error) return error

    const termo = (new URL(req.url).searchParams.get('q') || '').trim()
    if (termo.length < 2) return NextResponse.json([])

    return NextResponse.json(await depositoSuggest(termo))
  })
}

// POST /api/depositos  — cadastra um depósito (admin)
export async function POST(req: NextRequest) {
  return handle('depositos.POST', async () => {
    const { error } = await requireAdmin()
    if (error) return error

    const form = await readForm(req)
    if (form instanceof NextResponse) return form

    const deposito = String(form.get('deposito') || '').trim().toUpperCase()
    const localizacao = String(form.get('localizacao') || '').trim()

    if (!deposito) return jsonError('Nome do depósito é obrigatório.', 400)

    const result = await depositoSave(deposito, localizacao)
    return NextResponse.json(result, { status: result.success ? 201 : 400 })
  })
}
