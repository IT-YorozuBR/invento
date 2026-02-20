import { query, queryOne, execute } from './db'

// ============================================================
// TYPES
// ============================================================

export interface Usuario {
  id: number
  nome: string
  matricula: string
  tipo: 'admin' | 'operador' | 'supervisor'
  ativo: boolean
}

export interface Inventario {
  id: number
  codigo: string
  data_inicio: string
  data_fim: string | null
  descricao: string
  status: string
  admin_id: number
  admin_nome?: string
}

export interface Contagem {
  id: number
  inventario_id: number
  usuario_id: number
  deposito: string
  partnumber: string
  descricao_item: string | null
  unidade_medida: string
  lote: string | null
  validade: string | null
  quantidade_primaria: number
  quantidade_secundaria: number | null
  usuario_secundario_id: number | null
  quantidade_terceira: number | null
  usuario_terceiro_id: number | null
  quantidade_final: number | null
  data_contagem_primaria: string
  data_contagem_secundaria: string | null
  data_contagem_terceira: string | null
  status: string
  finalizado: boolean
  numero_contagens_realizadas: number
  pode_nova_contagem: boolean
  data_finalizacao: string | null
  observacoes: string | null
  usuario_nome?: string
  usuario_secundario_nome?: string
  usuario_terceiro_nome?: string
}

export interface Deposito {
  deposito: string
  localizacao: string | null
  total_registros: number
}

export interface Partnumber {
  partnumber: string
  descricao: string | null
  unidade_medida: string
  total_registros: number
}

export interface EstatisticasInventario {
  total: number
  concluidas: number
  divergentes: number
  pendentes: number
  terceiras: number
  partnumbers: number
  qtd_total: number
}

export interface PaginatedResult<T> {
  items: T[]
  page: number
  total_pages: number
  total: number
}

// ============================================================
// USUARIO MODEL
// ============================================================

export async function usuarioFindByMatricula(matricula: string, tipo?: string): Promise<Usuario | null> {
  let sql = 'SELECT id, nome, matricula, tipo FROM usuarios WHERE matricula = $1 AND ativo = true'
  const params: unknown[] = [matricula]
  let paramIndex = 2

  if (tipo) {
    sql += ` AND tipo = $${paramIndex}`
    params.push(tipo)
  }

  return queryOne<Usuario>(sql, params)
}

export async function usuarioFindOrCreate(nome: string, matricula: string, tipo: string = 'operador'): Promise<number | null> {
  const existing = await usuarioFindByMatricula(matricula)
  if (existing) {
    await execute('UPDATE usuarios SET ultimo_login = NOW() WHERE id = $1', [existing.id])
    return existing.id
  }

  const result = await query<{ id: number }>(
    'INSERT INTO usuarios (nome, matricula, tipo, ativo) VALUES ($1, $2, $3, true) RETURNING id',
    [nome, matricula, tipo]
  )

  return result[0]?.id || null
}

// ============================================================
// INVENTARIO MODEL
// ============================================================

export async function inventarioFindAtivo(): Promise<Inventario | null> {
  return queryOne<Inventario>(
    `SELECT i.id, i.codigo, i.data_inicio, i.data_fim, i.descricao, i.status, i.admin_id, u.nome AS admin_nome
     FROM inventarios i
     LEFT JOIN usuarios u ON i.admin_id = u.id
     WHERE i.status = 'aberto'
     ORDER BY i.data_inicio DESC
     LIMIT 1`
  )
}

export async function inventarioFindById(id: number): Promise<Inventario | null> {
  return queryOne<Inventario>(
    `SELECT i.id, i.codigo, i.data_inicio, i.data_fim, i.descricao, i.status, i.admin_id, u.nome AS admin_nome
     FROM inventarios i
     LEFT JOIN usuarios u ON i.admin_id = u.id
     WHERE i.id = $1`,
    [id]
  )
}

export async function inventarioFindConcluidos(page: number = 1, perPage: number = 10): Promise<PaginatedResult<Inventario>> {
  const offset = (page - 1) * perPage

  const countRows = await query<{ total: number }>(
    "SELECT COUNT(*) AS total FROM inventarios WHERE status IN ('fechado','cancelado')"
  )
  const total = countRows[0]?.total || 0

  const items = await query<Inventario>(
    `SELECT i.id, i.codigo, i.data_inicio, i.data_fim, i.descricao, i.status, i.admin_id, u.nome AS admin_nome
     FROM inventarios i
     LEFT JOIN usuarios u ON i.admin_id = u.id
     WHERE i.status IN ('fechado','cancelado')
     ORDER BY i.data_fim DESC, i.data_inicio DESC
     LIMIT $1 OFFSET $2`,
    [perPage, offset]
  )

  return {
    items,
    page,
    total_pages: total > 0 ? Math.ceil(total / perPage) : 1,
    total,
  }
}

export async function inventarioCreate(dataInicio: string, descricao: string, adminId: number): Promise<{ success: boolean; message: string; codigo?: string; id?: number }> {
  const codigo = 'INV-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + Math.random().toString(36).slice(-6).toUpperCase()

  await execute("UPDATE inventarios SET status = 'fechado' WHERE status = 'aberto'")

  const result = await query<{ id: number }>(
    "INSERT INTO inventarios (codigo, data_inicio, descricao, admin_id, status) VALUES ($1, $2, $3, $4, 'aberto') RETURNING id",
    [codigo, dataInicio, descricao, adminId]
  )

  if (result.length > 0) {
    return { success: true, message: 'Inventário criado com sucesso!', codigo, id: result[0].id }
  }
  return { success: false, message: 'Erro ao criar inventário' }
}

export async function inventarioFechar(id: number): Promise<{ success: boolean; message: string }> {
  const result = await query<{ affected_rows: number }>(
    "UPDATE inventarios SET status = 'fechado', data_fim = CURRENT_DATE WHERE id = $1 RETURNING 1 as affected_rows",
    [id]
  )

  if (result.length > 0) {
    return { success: true, message: 'Inventário fechado com sucesso!' }
  }
  return { success: false, message: 'Erro ao fechar inventário' }
}

export async function inventarioGetEstatisticas(inventarioId: number): Promise<EstatisticasInventario> {
  const row = await queryOne<Record<string, string>>(
    `SELECT
      COUNT(*)::INT AS total,
      SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END)::INT AS concluidas,
      SUM(CASE WHEN status = 'divergente' THEN 1 ELSE 0 END)::INT AS divergentes,
      SUM(CASE WHEN status = 'primaria' THEN 1 ELSE 0 END)::INT AS pendentes,
      SUM(CASE WHEN status = 'terceira' THEN 1 ELSE 0 END)::INT AS terceiras,
      COUNT(DISTINCT partnumber)::INT AS partnumbers,
      SUM(COALESCE(quantidade_final, quantidade_primaria))::DECIMAL(15,4) AS qtd_total
    FROM contagens
    WHERE inventario_id = $1`,
    [inventarioId]
  )

  return {
    total: Number(row?.total || 0),
    concluidas: Number(row?.concluidas || 0),
    divergentes: Number(row?.divergentes || 0),
    pendentes: Number(row?.pendentes || 0),
    terceiras: Number(row?.terceiras || 0),
    partnumbers: Number(row?.partnumbers || 0),
    qtd_total: Number(row?.qtd_total || 0),
  }
}

// ============================================================
// CONTAGEM MODEL
// ============================================================

export async function contagemFindById(id: number): Promise<Contagem | null> {
  return queryOne<Contagem>(
    `SELECT c.*,
            u1.nome AS usuario_nome,
            u2.nome AS usuario_secundario_nome,
            u3.nome AS usuario_terceiro_nome
     FROM contagens c
     LEFT JOIN usuarios u1 ON c.usuario_id = u1.id
     LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
     LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
     WHERE c.id = $1`,
    [id]
  )
}

export async function contagemFindByInventarioDeposito(inventarioId: number, deposito: string): Promise<Contagem[]> {
  return query<Contagem>(
    `SELECT c.*,
            u1.nome AS usuario_nome,
            u2.nome AS usuario_secundario_nome,
            u3.nome AS usuario_terceiro_nome
     FROM contagens c
     LEFT JOIN usuarios u1 ON c.usuario_id = u1.id
     LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
     LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
     WHERE c.inventario_id = $1 AND c.deposito = $2
     ORDER BY c.partnumber, c.lote`,
    [inventarioId, deposito]
  )
}

export async function contagemFindPaginated(
  inventarioId: number,
  page: number = 1,
  filters: { status?: string; partnumber?: string; deposito?: string } = {}
): Promise<PaginatedResult<Contagem>> {
  const perPage = parseInt(process.env.ITEMS_PER_PAGE || '20')
  const offset = (page - 1) * perPage

  let where = 'c.inventario_id = $1'
  const params: unknown[] = [inventarioId]
  let paramIndex = 2

  if (filters.status) {
    where += ` AND c.status = $${paramIndex++}`
    params.push(filters.status)
  }
  if (filters.partnumber) {
    where += ` AND c.partnumber ILIKE $${paramIndex++}`
    params.push(`%${filters.partnumber}%`)
  }
  if (filters.deposito) {
    where += ` AND c.deposito ILIKE $${paramIndex++}`
    params.push(`%${filters.deposito}%`)
  }

  const countRows = await query<{ total: number }>(
    `SELECT COUNT(*) AS total FROM contagens c WHERE ${where}`,
    params
  )
  const total = countRows[0]?.total || 0

  const items = await query<Contagem>(
    `SELECT c.*,
            u1.nome AS usuario_nome,
            u2.nome AS usuario_secundario_nome,
            u3.nome AS usuario_terceiro_nome
     FROM contagens c
     LEFT JOIN usuarios u1 ON c.usuario_id = u1.id
     LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
     LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
     WHERE ${where}
     ORDER BY c.data_contagem_primaria DESC
     LIMIT $${paramIndex++} OFFSET $${paramIndex++}`,
    [...params, perPage, offset]
  )

  return {
    items,
    page,
    total_pages: total > 0 ? Math.ceil(total / perPage) : 1,
    total,
  }
}

export async function contagemFindOpenByPartnumber(
  inventarioId: number,
  partnumber: string,
  deposito: string
): Promise<Contagem | null> {
  return queryOne<Contagem>(
    `SELECT * FROM contagens
     WHERE inventario_id = $1 AND partnumber = $2 AND deposito = $3 AND finalizado = false
     ORDER BY id DESC LIMIT 1`,
    [inventarioId, partnumber, deposito]
  )
}

export async function contagemVerificarFinalizado(
  inventarioId: number,
  partnumber: string,
  deposito: string
): Promise<{ finalizado: boolean; existe: boolean; id: number; status: string }> {
  const row = await queryOne<{ id: number; status: string; finalizado: boolean }>(
    `SELECT id, status, finalizado FROM contagens
     WHERE inventario_id = $1 AND partnumber = $2 AND deposito = $3
     ORDER BY id DESC LIMIT 1`,
    [inventarioId, partnumber, deposito]
  )

  if (!row) {
    return { finalizado: false, existe: false, id: 0, status: '' }
  }

  return {
    finalizado: !!row.finalizado,
    existe: true,
    id: row.id,
    status: row.status,
  }
}

export async function contagemCreate(data: Partial<Contagem>): Promise<{ success: boolean; message: string; id?: number }> {
  const result = await query<{ id: number }>(
    `INSERT INTO contagens (
      inventario_id, usuario_id, deposito, partnumber,
      descricao_item, unidade_medida, lote, validade,
      quantidade_primaria, localizacao, observacoes
    ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
    RETURNING id`,
    [
      data.inventario_id,
      data.usuario_id,
      data.deposito,
      data.partnumber,
      data.descricao_item || null,
      data.unidade_medida || 'UN',
      data.lote || null,
      data.validade || null,
      data.quantidade_primaria,
      (data as any).localizacao || null,
      data.observacoes || null,
    ]
  )

  if (result.length > 0) {
    return { success: true, message: 'Contagem criada com sucesso!', id: result[0].id }
  }
  return { success: false, message: 'Erro ao criar contagem' }
}

export async function contagemUpdate(id: number, updates: Partial<Contagem>): Promise<boolean> {
  const allowedFields = [
    'quantidade_primaria', 'quantidade_secundaria', 'quantidade_terceira',
    'quantidade_final', 'status', 'finalizado', 'data_contagem_secundaria',
    'data_contagem_terceira', 'usuario_secundario_id', 'usuario_terceiro_id',
    'motivo_divergencia', 'observacoes', 'precisa_segunda_contagem',
    'precisa_terceira_contagem', 'numero_contagens_realizadas', 'pode_nova_contagem'
  ]

  const fields = Object.keys(updates).filter(k => allowedFields.includes(k))
  if (fields.length === 0) return false

  const setClauses = fields.map((f, i) => `${f} = $${i + 1}`).join(', ')
  const values = fields.map(f => (updates as any)[f])
  values.push(id)

  const result = await query<{ affected_rows: number }>(
    `UPDATE contagens SET ${setClauses}, data_atualizacao = NOW() WHERE id = $${fields.length + 1} RETURNING 1 as affected_rows`,
    values
  )

  return result.length > 0
}

export async function contagemExportar(inventarioId: number, status?: string): Promise<Contagem[]> {
  let sql = `SELECT c.*,
            u1.nome AS usuario_nome,
            u2.nome AS usuario_secundario_nome,
            u3.nome AS usuario_terceiro_nome
     FROM contagens c
     LEFT JOIN usuarios u1 ON c.usuario_id = u1.id
     LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
     LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
     WHERE c.inventario_id = $1`

  const params: unknown[] = [inventarioId]

  if (status) {
    sql += ` AND c.status = $2`
    params.push(status)
  }

  sql += ` ORDER BY c.deposito, c.partnumber`

  return query<Contagem>(sql, params)
}

export async function contagemFindPendentes(inventarioId: number): Promise<Contagem[]> {
  return query<Contagem>(
    `SELECT c.*,
            u1.nome AS usuario_nome,
            u2.nome AS usuario_secundario_nome,
            u3.nome AS usuario_terceiro_nome
     FROM contagens c
     LEFT JOIN usuarios u1 ON c.usuario_id = u1.id
     LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
     LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
     WHERE c.inventario_id = $1 AND c.status = 'primaria'
     ORDER BY c.data_contagem_primaria DESC
     LIMIT 20`,
    [inventarioId]
  )
}

// ============================================================
// CONTAGEM — LÓGICA DE REGISTRO (portada do MySQL)
// ============================================================

export async function contagemRegistrarPrimaria(
  inventarioId: number,
  usuarioId: number,
  deposito: string,
  partnumber: string,
  quantidade: number,
  extra: { descricao?: string; unidade?: string; lote?: string | null; validade?: string | null }
): Promise<{ success: boolean; message: string; fase?: number; status?: string; quantidade_final?: number | null; convergente?: boolean }> {
  const lote = extra.lote || null

  let sql = 'SELECT * FROM contagens WHERE inventario_id = $1 AND deposito = $2 AND partnumber = $3'
  const params: unknown[] = [inventarioId, deposito, partnumber]

  if (lote !== null) {
    sql += ' AND lote = $4'
    params.push(lote)
  } else {
    sql += ' AND (lote IS NULL OR lote = \'\')'
  }

  const existing = await queryOne<Contagem>(sql, params)

  if (existing) {
    const podeNova = !!existing.pode_nova_contagem
    const finalizado = !!existing.finalizado

    if (finalizado) {
      return { success: false, message: 'Esta contagem já foi finalizada.' }
    }

    if (podeNova) {
      return avancarParaProximaFase(existing, quantidade, usuarioId)
    }

    return somarNaFaseAtual(existing, quantidade)
  }

  return criarPrimeiraContagem(inventarioId, usuarioId, deposito, partnumber, quantidade, extra)
}

async function somarNaFaseAtual(
  row: Contagem,
  quantidade: number
): Promise<{ success: boolean; message: string; fase?: number }> {
  const contagemId = row.id
  const numContagens = row.numero_contagens_realizadas

  if (numContagens === 1) {
    const nova = Number(row.quantidade_primaria) + quantidade
    await execute(
      "UPDATE contagens SET quantidade_primaria = $1, data_contagem_primaria = NOW(), status = 'primaria' WHERE id = $2",
      [nova, contagemId]
    )
    return {
      success: true,
      fase: 1,
      message: `✔ Somado à primeira contagem! Total: ${formatNum(nova)} un (Aguardando admin liberar segunda contagem)`,
    }
  } else if (numContagens === 2) {
    const nova = Number(row.quantidade_secundaria || 0) + quantidade
    await execute(
      "UPDATE contagens SET quantidade_secundaria = $1, data_contagem_secundaria = NOW(), status = 'secundaria' WHERE id = $2",
      [nova, contagemId]
    )
    return {
      success: true,
      fase: 2,
      message: `✔ Somado à contagem! Total: ${formatNum(nova)}`,
    }
  } else if (numContagens === 3) {
    return { success: false, message: 'Contagem já possui 3 registros.' }
  }

  return { success: false, message: 'Estado inválido da contagem.' }
}

async function avancarParaProximaFase(
  row: Contagem,
  quantidade: number,
  usuarioId: number
): Promise<{ success: boolean; message: string; fase?: number; status?: string; quantidade_final?: number | null; convergente?: boolean }> {
  const numContagens = row.numero_contagens_realizadas

  if (numContagens === 1) {
    return registrarSegundaFase(row.id, quantidade, usuarioId)
  } else if (numContagens === 2) {
    return registrarTerceiraFase(row, quantidade, usuarioId)
  }

  return { success: false, message: 'Número máximo de contagens atingido' }
}

async function registrarSegundaFase(
  contagemId: number,
  quantidade: number,
  usuarioId: number
): Promise<{ success: boolean; message: string; fase?: number }> {
  const result = await query<{ id: number }>(
    `UPDATE contagens
     SET quantidade_secundaria = $1, usuario_secundario_id = $2,
         data_contagem_secundaria = NOW(), status = 'secundaria',
         numero_contagens_realizadas = 2, pode_nova_contagem = false
     WHERE id = $3
     RETURNING id`,
    [quantidade, usuarioId, contagemId]
  )

  if (result.length > 0) {
    return {
      success: true,
      fase: 2,
      message: `✔ Contagem registrada! Quantidade: ${formatNum(quantidade)}`,
    }
  }

  return { success: false, message: 'Erro ao registrar segunda contagem' }
}

async function registrarTerceiraFase(
  row: Contagem,
  quantidade: number,
  usuarioId: number
): Promise<{ success: boolean; message: string; fase?: number; status?: string; quantidade_final?: number | null; convergente?: boolean }> {
  const contagemId = row.id
  const primaria = Number(row.quantidade_primaria)
  const secundaria = Number(row.quantidade_secundaria)
  const terceira = quantidade

  let quantidadeFinal: number | null = null
  let status = ''
  let mensagem = ''

  if (Math.abs(primaria - secundaria) < 0.0001 || Math.abs(primaria - terceira) < 0.0001) {
    quantidadeFinal = primaria
    status = 'concluida'
    mensagem = `✅ Contagem concluída! Quantidade validada: ${formatNum(quantidadeFinal)} un`
  } else if (Math.abs(secundaria - terceira) < 0.0001) {
    quantidadeFinal = secundaria
    status = 'concluida'
    mensagem = `✅ Contagem concluída! Quantidade validada: ${formatNum(quantidadeFinal)} un`
  } else {
    status = 'divergente'
    quantidadeFinal = null
    mensagem = `⚠️ Contagem encerrada. Por favor, aguarde o administrador.`
  }

  let result: { id: number }[]

  if (quantidadeFinal !== null) {
    result = await query<{ id: number }>(
      `UPDATE contagens
       SET quantidade_terceira = $1, usuario_terceiro_id = $2, quantidade_final = $3,
           data_contagem_terceira = NOW(), status = $4, numero_contagens_realizadas = 3,
           finalizado = true, pode_nova_contagem = false, data_finalizacao = NOW()
       WHERE id = $5
       RETURNING id`,
      [terceira, usuarioId, quantidadeFinal, status, contagemId]
    )
  } else {
    result = await query<{ id: number }>(
      `UPDATE contagens
       SET quantidade_terceira = $1, usuario_terceiro_id = $2,
           data_contagem_terceira = NOW(), status = $3, numero_contagens_realizadas = 3,
           finalizado = true, pode_nova_contagem = false, data_finalizacao = NOW()
       WHERE id = $4
       RETURNING id`,
      [terceira, usuarioId, status, contagemId]
    )
  }

  if (result.length > 0) {
    return {
      success: true,
      fase: 3,
      message: mensagem,
      status: status,
      quantidade_final: quantidadeFinal,
      convergente: quantidadeFinal !== null,
    }
  }

  return { success: false, message: 'Erro ao registrar terceira contagem' }
}

async function criarPrimeiraContagem(
  inventarioId: number,
  usuarioId: number,
  deposito: string,
  partnumber: string,
  quantidade: number,
  extra: { descricao?: string; unidade?: string; lote?: string | null; validade?: string | null }
): Promise<{ success: boolean; message: string; fase?: number }> {
  const descricao = extra.descricao || null
  const unidade = extra.unidade || 'UN'
  const lote = extra.lote || null
  const validade = extra.validade || null

  const result = await query<{ id: number }>(
    `INSERT INTO contagens
      (inventario_id, usuario_id, deposito, partnumber, quantidade_primaria,
       descricao_item, unidade_medida, lote, validade, status,
       numero_contagens_realizadas, pode_nova_contagem, finalizado)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, 'primaria', 1, false, false)
     RETURNING id`,
    [inventarioId, usuarioId, deposito, partnumber, quantidade, descricao, unidade, lote, validade]
  )

  if (result.length > 0) {
    return {
      success: true,
      fase: 1,
      message: `✔ Contagem registrada! Quantidade: ${formatNum(quantidade)}`,
    }
  }

  return { success: false, message: 'Erro ao registrar contagem' }
}

// ============================================================
// CONTAGEM — ADMIN ACTIONS
// ============================================================

export async function contagemIniciarSegundaContagem(contagemId: number): Promise<{ success: boolean; message: string; partnumber?: string; deposito?: string }> {
  const row = await contagemFindById(contagemId)
  if (!row) return { success: false, message: 'Contagem não encontrada.' }
  if (row.numero_contagens_realizadas !== 1) return { success: false, message: 'Só pode iniciar segunda contagem após primeira estar completa.' }
  if (row.finalizado) return { success: false, message: 'Contagem já finalizada.' }

  const result = await query<{ id: number }>(
    'UPDATE contagens SET pode_nova_contagem = true WHERE id = $1 RETURNING id',
    [contagemId]
  )

  if (result.length > 0) {
    return { success: true, message: '✅ Segunda contagem liberada! O operador pode realizar a contagem agora.', partnumber: row.partnumber, deposito: row.deposito }
  }
  return { success: false, message: 'Erro ao liberar segunda contagem.' }
}

export async function contagemIniciarTerceiraContagem(contagemId: number): Promise<{ success: boolean; message: string; partnumber?: string; deposito?: string }> {
  const row = await contagemFindById(contagemId)
  if (!row) return { success: false, message: 'Contagem não encontrada.' }
  if (row.numero_contagens_realizadas !== 2) return { success: false, message: 'Só pode iniciar terceira contagem após segunda estar completa.' }
  if (row.finalizado) return { success: false, message: 'Contagem já finalizada.' }

  const result = await query<{ id: number }>(
    'UPDATE contagens SET pode_nova_contagem = true WHERE id = $1 RETURNING id',
    [contagemId]
  )

  if (result.length > 0) {
    return { success: true, message: '✅ Terceira contagem liberada! O operador pode realizar a contagem final agora.', partnumber: row.partnumber, deposito: row.deposito }
  }
  return { success: false, message: 'Erro ao liberar terceira contagem.' }
}

export async function contagemFinalizar(contagemId: number): Promise<{ success: boolean; message: string }> {
  const row = await contagemFindById(contagemId)
  if (!row) return { success: false, message: 'Contagem não encontrada.' }
  if (row.finalizado) return { success: false, message: 'Esta contagem já foi encerrada.' }

  const num = row.numero_contagens_realizadas
  const qtd1 = Number(row.quantidade_primaria)
  const qtd2 = row.quantidade_secundaria !== null ? Number(row.quantidade_secundaria) : null

  let status: string
  let quantidadeFinal: number | null

  if (num === 1) {
    // Só uma contagem: admin confirma → concluida
    status = 'concluida'
    quantidadeFinal = qtd1
  } else if (num === 2 && qtd2 !== null) {
    // Duas contagens: compara
    if (Math.abs(qtd1 - qtd2) < 0.0001) {
      status = 'concluida'
      quantidadeFinal = qtd1
    } else {
      status = 'divergente'
      quantidadeFinal = null
    }
  } else {
    // 3 contagens ou estado inesperado: mantém o status atual
    status = row.status
    quantidadeFinal = row.quantidade_final
  }

  const result = await query<{ id: number }>(
    `UPDATE contagens
     SET finalizado = true, pode_nova_contagem = false, data_finalizacao = NOW(),
         status = $2, quantidade_final = $3
     WHERE id = $1
     RETURNING id`,
    [contagemId, status, quantidadeFinal]
  )

  if (result.length > 0) {
    const msg = status === 'divergente'
      ? `Contagem encerrada com divergência! Partnumber "${row.partnumber}".`
      : `Contagem concluída! Partnumber "${row.partnumber}" finalizado.`
    return { success: true, message: msg }
  }
  return { success: false, message: 'Erro ao encerrar contagem.' }
}

// ============================================================
// CONTAGEM — EXPORTAÇÃO
// ============================================================

export async function contagemExportarDados(inventarioId: number): Promise<Contagem[]> {
  return query<Contagem>(
    `SELECT c.deposito, c.partnumber, c.descricao_item, c.unidade_medida,
        c.lote, c.validade,
        c.quantidade_primaria, c.quantidade_secundaria,
        c.quantidade_terceira, c.quantidade_final,
        c.status, c.finalizado, c.data_finalizacao,
        u1.nome AS usuario_nome,
        u2.nome AS usuario_secundario_nome,
        u3.nome AS usuario_terceiro_nome,
        c.data_contagem_primaria, c.data_contagem_secundaria,
        c.data_contagem_terceira, c.observacoes
     FROM contagens c
     LEFT JOIN usuarios u1 ON c.usuario_id = u1.id
     LEFT JOIN usuarios u2 ON c.usuario_secundario_id = u2.id
     LEFT JOIN usuarios u3 ON c.usuario_terceiro_id = u3.id
     WHERE c.inventario_id = $1
     ORDER BY c.deposito, c.partnumber`,
    [inventarioId]
  )
}

export async function contagemExportarConsolidados(inventarioId: number): Promise<unknown[]> {
  return query(
    `SELECT
       c.partnumber, c.descricao_item, c.unidade_medida,
       SUM(COALESCE(c.quantidade_final, c.quantidade_primaria))::DECIMAL(15,4) AS quantidade_total,
       COUNT(c.id)::INT AS num_depositos,
       STRING_AGG(
         c.deposito || ': ' || COALESCE(c.quantidade_final, c.quantidade_primaria)::TEXT,
         ' | '
         ORDER BY c.deposito
       ) AS detalhes_depositos
     FROM contagens c
     WHERE c.inventario_id = $1
     GROUP BY c.partnumber, c.descricao_item, c.unidade_medida
     ORDER BY c.partnumber`,
    [inventarioId]
  )
}

// ============================================================
// DEPOSITO MODEL
// ============================================================

export async function depositoAll(): Promise<Deposito[]> {
  return query<Deposito>(
    'SELECT deposito, localizacao, total_registros FROM depositos_registrados ORDER BY deposito ASC'
  )
}

export async function depositoSuggest(term: string): Promise<Deposito[]> {
  return query<Deposito>(
    'SELECT deposito, localizacao FROM depositos_registrados WHERE deposito ILIKE $1 ORDER BY total_registros DESC, data_ultimo_registro DESC LIMIT 10',
    [`%${term}%`]
  )
}

export async function depositoSave(deposito: string, localizacao: string = ''): Promise<{ success: boolean; message: string }> {
  const result = await query<{ id: number }>(
    `INSERT INTO depositos_registrados (deposito, localizacao)
     VALUES ($1, $2)
     ON CONFLICT (deposito) DO UPDATE SET localizacao = COALESCE($2, depositos_registrados.localizacao)
     RETURNING id`,
    [deposito, localizacao || null]
  )

  if (result.length > 0) {
    return { success: true, message: 'Depósito salvo com sucesso!' }
  }
  return { success: false, message: 'Erro ao salvar depósito' }
}

export async function depositoDelete(deposito: string): Promise<{ success: boolean; message: string }> {
  const result = await query<{ id: number }>(
    'DELETE FROM depositos_registrados WHERE deposito = $1 RETURNING 1 as id',
    [deposito]
  )

  if (result.length > 0) {
    return { success: true, message: 'Depósito excluído com sucesso!' }
  }
  return { success: false, message: 'Erro ao excluir depósito' }
}

export async function depositoTouch(deposito: string, localizacao: string = ''): Promise<void> {
  await query(
    `INSERT INTO depositos_registrados (deposito, localizacao)
     VALUES ($1, $2)
     ON CONFLICT (deposito) DO UPDATE SET
       total_registros = depositos_registrados.total_registros + 1,
       data_ultimo_registro = NOW()`,
    [deposito, localizacao || null]
  )
}

// ============================================================
// PARTNUMBER MODEL
// ============================================================

export async function partnumberAll(): Promise<Partnumber[]> {
  return query<Partnumber>(
    'SELECT partnumber, descricao, unidade_medida, total_registros FROM partnumbers_registrados ORDER BY partnumber ASC'
  )
}

export async function partnumberSuggest(term: string): Promise<Partnumber[]> {
  return query<Partnumber>(
    'SELECT partnumber, descricao FROM partnumbers_registrados WHERE partnumber ILIKE $1 ORDER BY total_registros DESC, data_ultimo_registro DESC LIMIT 10',
    [`%${term}%`]
  )
}

export async function partnumberSave(pn: string, descricao: string = '', unidade: string = 'UN'): Promise<{ success: boolean; message: string }> {
  if (!/^[A-Za-z0-9\-_]{3,50}$/.test(pn)) {
    return { success: false, message: 'Part number inválido (use letras, números, - e _)' }
  }

  const result = await query<{ id: number }>(
    `INSERT INTO partnumbers_registrados (partnumber, descricao, unidade_medida)
     VALUES ($1, $2, $3)
     ON CONFLICT (partnumber) DO UPDATE SET
       descricao = COALESCE($2, partnumbers_registrados.descricao),
       unidade_medida = COALESCE($3, partnumbers_registrados.unidade_medida),
       total_registros = partnumbers_registrados.total_registros + 1,
       data_ultimo_registro = NOW()
     RETURNING id`,
    [pn, descricao || null, unidade]
  )

  if (result.length > 0) {
    return { success: true, message: 'Part number salvo com sucesso!' }
  }
  return { success: false, message: 'Erro ao salvar part number' }
}

export async function partnumberDelete(pn: string): Promise<{ success: boolean; message: string }> {
  const result = await query<{ id: number }>(
    'DELETE FROM partnumbers_registrados WHERE partnumber = $1 RETURNING 1 as id',
    [pn]
  )

  if (result.length > 0) {
    return { success: true, message: 'Part number excluído com sucesso!' }
  }
  return { success: false, message: 'Erro ao excluir part number' }
}

export async function partnumberTouch(pn: string, descricao: string = ''): Promise<void> {
  await query(
    `INSERT INTO partnumbers_registrados (partnumber, descricao)
     VALUES ($1, $2)
     ON CONFLICT (partnumber) DO UPDATE SET
       total_registros = partnumbers_registrados.total_registros + 1,
       data_ultimo_registro = NOW()`,
    [pn, descricao || null]
  )
}

// ============================================================
// NOTIFICACAO MODEL
// ============================================================

export async function notificacaoCriar(
  inventarioId: number,
  usuarioNome: string,
  partnumber: string,
  deposito: string,
  fase: number = 1
): Promise<void> {
  await query(
    'INSERT INTO notificacoes_admin (inventario_id, usuario_nome, partnumber, deposito, fase) VALUES ($1, $2, $3, $4, $5)',
    [inventarioId, usuarioNome, partnumber, deposito, fase]
  )
}

export async function notificacaoBuscarDesde(
  inventarioId: number,
  desde: number
): Promise<{ total: number; items: unknown[] }> {
  const desdeTs = new Date(desde * 1000).toISOString()
  const items = await query(
    `SELECT usuario_nome, partnumber, deposito, fase,
            EXTRACT(EPOCH FROM criado_em)::INT AS ts
     FROM notificacoes_admin
     WHERE inventario_id = $1 AND criado_em > $2
     ORDER BY criado_em DESC
     LIMIT 20`,
    [inventarioId, desdeTs]
  )
  return { total: items.length, items }
}

// ============================================================
// HELPERS
// ============================================================

function formatNum(n: number): string {
  return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
