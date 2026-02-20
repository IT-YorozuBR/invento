import { NextRequest, NextResponse } from 'next/server'
import { getIronSession } from 'iron-session'
import { cookies } from 'next/headers'
import { sessionOptions, SessionData, isAdmin } from '@/lib/session'
import { inventarioFindById, contagemExportarDados, contagemExportarConsolidados } from '@/lib/models'
import * as XLSX from 'xlsx'

export async function GET(req: NextRequest) {
  const cookieStore = cookies()
  const session = await getIronSession<SessionData>(cookieStore, sessionOptions)

  if (!session.usuarioId || !isAdmin(session)) {
    return NextResponse.json({ error: 'Não autorizado.' }, { status: 403 })
  }

  const { searchParams } = new URL(req.url)
  const inventarioId = parseInt(searchParams.get('inventario_id') || '0')
  const formato = searchParams.get('formato') || 'xlsx'
  const tipo = searchParams.get('tipo') || 'detalhado'

  if (inventarioId <= 0) {
    return NextResponse.json({ error: 'ID do inventário inválido.' }, { status: 400 })
  }

  const inventario = await inventarioFindById(inventarioId)
  if (!inventario) {
    return NextResponse.json({ error: 'Inventário não encontrado.' }, { status: 404 })
  }

  const fmtDate = (d: string | null) => d ? new Date(d).toLocaleDateString('pt-BR') : '—'

  if (tipo === 'consolidado') {
    const rows = await contagemExportarConsolidados(inventarioId) as Record<string, unknown>[]
    const headers = ['Part Number', 'Descrição', 'Unidade', 'Qtd. Total', 'Nº Depósitos', 'Detalhes por Depósito']
    const meta: string[][] = [
      ['INVENTÁRIO', inventario.codigo],
      ['DESCRIÇÃO', inventario.descricao || ''],
      ['RELATÓRIO', 'CONSOLIDADO POR PARTNUMBER'],
      ['DATA GERAÇÃO', new Date().toLocaleString('pt-BR')],
      [],
    ]
    const dataRows = rows.map(r => [
      r.partnumber, r.descricao_item, r.unidade_medida,
      r.quantidade_total, r.num_depositos, r.detalhes_depositos,
    ])
    return buildExport(`inventario-consolidado-${inventario.codigo}`, formato, meta, headers, dataRows as unknown[][])
  }

  const rows = await contagemExportarDados(inventarioId) as Record<string, unknown>[]
  const headers = [
    'Depósito', 'Part Number', 'Descrição', 'Unidade', 'Lote', 'Validade',
    'Qtd. 1ª Contagem', 'Qtd. 2ª Contagem', 'Qtd. 3ª Contagem', 'Qtd. Final',
    'Status', 'Contador 1º', 'Contador 2º', 'Contador 3º',
    'Data 1ª Contagem', 'Data 2ª Contagem', 'Data 3ª Contagem', 'Observações',
  ]
  const meta: string[][] = [
    ['INVENTÁRIO', inventario.codigo],
    ['DESCRIÇÃO', inventario.descricao || ''],
    ['DATA INÍCIO', fmtDate(inventario.data_inicio)],
    ['DATA FIM', fmtDate(inventario.data_fim)],
    ['ADMINISTRADOR', inventario.admin_nome || ''],
    ['STATUS', inventario.status],
    [],
  ]
  const dataRows = rows.map(r => [
    r.deposito, r.partnumber, r.descricao_item, r.unidade_medida,
    r.lote, r.validade,
    r.quantidade_primaria, r.quantidade_secundaria,
    r.quantidade_terceira, r.quantidade_final,
    r.status,
    // Nomes corretos no postgres (não mais contador_1/2/3)
    (r as any).usuario_nome,
    (r as any).usuario_secundario_nome,
    (r as any).usuario_terceiro_nome,
    r.data_contagem_primaria ? new Date(r.data_contagem_primaria as string).toLocaleString('pt-BR') : '',
    r.data_contagem_secundaria ? new Date(r.data_contagem_secundaria as string).toLocaleString('pt-BR') : '',
    r.data_contagem_terceira ? new Date(r.data_contagem_terceira as string).toLocaleString('pt-BR') : '',
    r.observacoes,
  ])

  return buildExport(`inventario-${inventario.codigo}`, formato, meta, headers, dataRows as unknown[][])
}

function buildExport(
  filename: string,
  formato: string,
  meta: unknown[][],
  headers: string[],
  dataRows: unknown[][]
): NextResponse {
  if (formato === 'csv') {
    const allRows = [...meta, headers, ...dataRows]
    const csvContent = allRows.map(row =>
      (row as unknown[]).map(cell => {
        const val = String(cell ?? '')
        return val.includes(';') || val.includes('"') || val.includes('\n')
          ? `"${val.replace(/"/g, '""')}"` : val
      }).join(';')
    ).join('\r\n')

    return new NextResponse(csvContent, {
      headers: {
        'Content-Type': 'text/csv; charset=utf-8',
        'Content-Disposition': `attachment; filename="${filename}.csv"`,
      }
    })
  }

  if (formato === 'txt') {
    const widths = headers.map((h, i) => {
      const colVals = [h, ...dataRows.map(r => String(r[i] ?? ''))]
      return Math.min(30, Math.max(...colVals.map(v => v.length)))
    })
    const separator = widths.map(w => '-'.repeat(w + 2)).join('+')

    const lines: string[] = []
    meta.forEach(r => lines.push((r as string[]).join(': ')))
    lines.push(separator)
    lines.push(headers.map((h, i) => h.padEnd(widths[i])).join(' | '))
    lines.push(separator)
    dataRows.forEach(r => {
      lines.push((r as unknown[]).map((v, i) => String(v ?? '').padEnd(widths[i])).join(' | '))
    })
    lines.push(separator)

    return new NextResponse(lines.join('\r\n'), {
      headers: {
        'Content-Type': 'text/plain; charset=utf-8',
        'Content-Disposition': `attachment; filename="${filename}.txt"`,
      }
    })
  }

  // Default: XLSX
  const wb = XLSX.utils.book_new()
  const ws = XLSX.utils.aoa_to_sheet([...meta, headers, ...dataRows])
  XLSX.utils.book_append_sheet(wb, ws, 'Inventário')

  const buf = XLSX.write(wb, { type: 'buffer', bookType: 'xlsx' })
  return new NextResponse(buf, {
    headers: {
      'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'Content-Disposition': `attachment; filename="${filename}.xlsx"`,
    }
  })
}