import { Pool, PoolClient } from 'pg'

// Pool global de conexões (singleton)
let pool: Pool | null = null

export function getPool(): Pool {
  if (!pool) {
    const connectionString = process.env.DATABASE_URL

    if (!connectionString) {
      throw new Error('DATABASE_URL environment variable is not set')
    }

    pool = new Pool({
      connectionString,
      max: 10,
      // Tempo máximo que uma conexão idle fica no pool antes de ser fechada
      // Menor que o timeout padrão do servidor (evita "connection terminated")
      idleTimeoutMillis: 10_000,
      // Tempo máximo para OBTER uma conexão do pool
      connectionTimeoutMillis: 5_000,
      // Revalida a conexão antes de usá-la (evita conexões mortas)
      allowExitOnIdle: false,
      ssl: { rejectUnauthorized: false },
    })

    pool.on('error', (err) => {
      console.error('[db] Erro em conexão idle:', err.message)
      // Descarta o pool para forçar recriação na próxima requisição
      pool = null
    })
  }
  return pool
}

// Executa a query com retry automático em caso de conexão morta
async function queryWithRetry<T>(
  fn: (p: Pool) => Promise<T>,
  retries = 2
): Promise<T> {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      return await fn(getPool())
    } catch (err: any) {
      const isConnectionError =
        err.code === 'ECONNRESET' ||
        err.code === 'ECONNREFUSED' ||
        err.code === 'ETIMEDOUT' ||
        err.message?.includes('Connection terminated') ||
        err.message?.includes('connection timeout') ||
        err.message?.includes('terminating connection')

      if (isConnectionError && attempt < retries) {
        console.warn(`[db] Tentativa ${attempt} falhou (${err.message}). Reconectando...`)
        // Descarta o pool com problema para criar um novo
        if (pool) {
          pool.end().catch(() => {})
          pool = null
        }
        // Pequena espera antes de tentar novamente
        await new Promise(r => setTimeout(r, 200 * attempt))
        continue
      }

      throw err
    }
  }
  // TypeScript exige um return aqui, mas o loop sempre retorna ou lança
  throw new Error('Unreachable')
}

export async function query<T = unknown>(
  sql: string,
  params: unknown[] = []
): Promise<T[]> {
  return queryWithRetry(async (p) => {
    const result = await p.query(sql, params)
    return result.rows as T[]
  })
}

export async function queryOne<T = unknown>(
  sql: string,
  params: unknown[] = []
): Promise<T | null> {
  const rows = await query<T>(sql, params)
  return rows[0] ?? null
}

export async function execute(
  sql: string,
  params: unknown[] = []
): Promise<{ affectedRows: number; insertId?: number }> {
  return queryWithRetry(async (p) => {
    const result = await p.query(sql, params)
    return {
      affectedRows: result.rowCount || 0,
      insertId: result.rows[0]?.id,
    }
  })
}

// Helper para desconectar (usar ao finalizar a aplicação)
export async function closePool(): Promise<void> {
  if (pool) {
    await pool.end()
    pool = null
  }
}

// Para migrations, às vezes precisamos de uma conexão direta sem pool
export async function getDirectConnection(): Promise<PoolClient> {
  return queryWithRetry(async (p) => p.connect())
}