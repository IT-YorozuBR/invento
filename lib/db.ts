import { Pool, PoolClient } from 'pg'

// Pool global de conexões (singleton)
let pool: Pool | null = null

// Bancos gerenciados (Neon, RDS etc.) exigem SSL; um Postgres em container
// próprio (ex.: docker-compose na mesma rede) normalmente não tem TLS habilitado.
// Controlado por DATABASE_SSL=true|false — default: desligado (self-hosted).
export function resolveSslConfig(): false | { rejectUnauthorized: boolean } {
  return process.env.DATABASE_SSL === 'true' ? { rejectUnauthorized: false } : false
}

export interface PgConnConfig {
  host: string
  port: number
  user: string
  password: string
  database: string
}

// Resolve a configuração de conexão a partir de variáveis discretas
// (PGHOST/PGUSER/PGPASSWORD/PGDATABASE/PGPORT), se presentes, ou por
// parse de DATABASE_URL como alternativa. Variáveis discretas evitam
// que caracteres especiais na senha (/, +, @, #...) quebrem o parser
// de URL — problema real com senhas geradas aleatoriamente.
export function resolvePgConfig(): PgConnConfig {
  if (process.env.PGHOST) {
    return {
      host: process.env.PGHOST,
      port: parseInt(process.env.PGPORT || '5432'),
      user: process.env.PGUSER || 'postgres',
      password: process.env.PGPASSWORD || '',
      database: process.env.PGDATABASE || 'postgres',
    }
  }

  const connectionString = process.env.DATABASE_URL
  if (!connectionString) {
    throw new Error('Defina DATABASE_URL ou PGHOST/PGUSER/PGPASSWORD/PGDATABASE.')
  }

  const url = new URL(connectionString)
  return {
    host: url.hostname,
    port: parseInt(url.port) || 5432,
    user: decodeURIComponent(url.username),
    password: decodeURIComponent(url.password),
    database: url.pathname.slice(1),
  }
}

export function getPool(): Pool {
  if (!pool) {
    pool = new Pool({
      ...resolvePgConfig(),
      max: 10,
      // Tempo máximo que uma conexão idle fica no pool antes de ser fechada
      // Menor que o timeout padrão do servidor (evita "connection terminated")
      idleTimeoutMillis: 10_000,
      // Tempo máximo para OBTER uma conexão do pool
      connectionTimeoutMillis: 5_000,
      // Revalida a conexão antes de usá-la (evita conexões mortas)
      allowExitOnIdle: false,
      ssl: resolveSslConfig(),
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

// Executa fn dentro de uma transação (BEGIN/COMMIT/ROLLBACK), liberando a conexão ao final
export async function withTransaction<T>(fn: (client: PoolClient) => Promise<T>): Promise<T> {
  const client = await getPool().connect()
  try {
    await client.query('BEGIN')
    const result = await fn(client)
    await client.query('COMMIT')
    return result
  } catch (err) {
    await client.query('ROLLBACK').catch(() => {})
    throw err
  } finally {
    client.release()
  }
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