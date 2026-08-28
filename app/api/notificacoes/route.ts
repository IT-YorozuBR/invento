import { NextRequest, NextResponse } from 'next/server'
import { requireAuth, handle } from '@/lib/api'
import { inventarioFindAtivo, notificacaoBuscarDesde } from '@/lib/models'

// GET /api/notificacoes?desde=<unix_ts>  — polling de novas contagens (admin)
export async function GET(req: NextRequest) {
  return handle('notificacoes.GET', async () => {
    const { error } = await requireAuth()
    if (error) return error

    const desde = parseInt(new URL(req.url).searchParams.get('desde') || '0')

    const inventario = await inventarioFindAtivo()
    if (!inventario) {
      return NextResponse.json({ total: 0, items: [] })
    }

    const result = await notificacaoBuscarDesde(inventario.id, desde)
    return NextResponse.json(result)
  })
}
