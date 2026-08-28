import { NextResponse } from 'next/server'
import { IronSession } from 'iron-session'
import { getSession, SessionData, isAdmin } from '@/lib/session'

/**
 * Helpers compartilhados pelas rotas de API.
 *
 * Convenção de respostas:
 *   - sucesso  → 2xx  { ...dados }            (ou { success: true, message })
 *   - erro     → 4xx/5xx { error: string }     ou { success: false, message }
 */

export const jsonError = (message: string, status: number) =>
  NextResponse.json({ error: message }, { status })

/**
 * Lê o corpo `multipart/form-data` / `x-www-form-urlencoded` da requisição.
 * Em vez de deixar `req.formData()` estourar um 500 quando o corpo está
 * ausente ou com Content-Type errado, devolve um 400 padronizado.
 *
 * Uso: `const form = await readForm(req); if (form instanceof NextResponse) return form`
 */
export async function readForm(req: Request): Promise<FormData | NextResponse> {
  try {
    return await req.formData()
  } catch {
    return jsonError('Corpo inválido: esperado multipart/form-data.', 400)
  }
}

/** Converte um segmento de rota em id inteiro positivo, ou `null` se inválido. */
export function parseId(raw: string | undefined): number | null {
  const n = Number(raw)
  return Number.isInteger(n) && n > 0 ? n : null
}

type AuthOk = { session: IronSession<SessionData>; error: null }
type AuthErr = { session: null; error: NextResponse }

/** Exige um usuário autenticado. Uso: `const { session, error } = await requireAuth(); if (error) return error` */
export async function requireAuth(): Promise<AuthOk | AuthErr> {
  const session = await getSession()
  if (!session.usuarioId) {
    return { session: null, error: jsonError('Não autenticado.', 401) }
  }
  return { session, error: null }
}

/** Exige um usuário autenticado E administrador. */
export async function requireAdmin(): Promise<AuthOk | AuthErr> {
  const auth = await requireAuth()
  if (auth.error) return auth
  if (!isAdmin(auth.session)) {
    return { session: null, error: jsonError('Apenas administradores.', 403) }
  }
  return auth
}

/**
 * Envolve o corpo de um handler garantindo um 500 padronizado em caso de
 * exceção não tratada (ex.: falha no banco).
 */
export async function handle(
  scope: string,
  fn: () => Promise<NextResponse>
): Promise<NextResponse> {
  try {
    return await fn()
  } catch (err: any) {
    console.error(`[api:${scope}]`, err)
    return jsonError(err?.message || 'Erro interno do servidor.', 500)
  }
}
