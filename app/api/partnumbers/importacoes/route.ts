import { NextRequest, NextResponse } from 'next/server'
import { requireAdmin, handle, jsonError, readForm } from '@/lib/api'
import { partnumberSave } from '@/lib/models'

// POST /api/partnumbers/importacoes  — importa part numbers a partir de um CSV (admin)
// multipart/form-data: campo "arquivo_csv"
export async function POST(req: NextRequest) {
  return handle('partnumbers.importacoes.POST', async () => {
    const { error } = await requireAdmin()
    if (error) return error

    const form = await readForm(req)
    if (form instanceof NextResponse) return form

    const file = form.get('arquivo_csv')
    if (!file || typeof file === 'string') {
      return jsonError('Arquivo inválido.', 400)
    }

    const lines = (await (file as File).text()).split('\n')
    let sucessos = 0
    let erros = 0
    const mensagens: string[] = []

    for (let i = 1; i < lines.length; i++) {
      const line = lines[i].trim()
      if (!line) continue
      const cols = line.split(';')
      const pn = (cols[0] || '').trim()
      if (!pn) { erros++; continue }

      const result = await partnumberSave(pn, (cols[1] || '').trim(), (cols[2] || 'UN').trim())
      if (result.success) {
        sucessos++
      } else {
        erros++
        mensagens.push(`Linha ${i + 1}: ${result.message}`)
      }
    }

    return NextResponse.json({
      success: true,
      message: `Importados: ${sucessos} | Erros: ${erros}`,
      sucessos,
      erros,
      mensagens,
    })
  })
}
