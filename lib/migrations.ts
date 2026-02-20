import { Pool } from 'pg'

/**
 * Executa as migrations do banco de dados no PostgreSQL.
 */
export async function runMigrations(): Promise<void> {
  const connectionUrl = process.env.DATABASE_URL

  if (!connectionUrl) {
    throw new Error('DATABASE_URL environment variable is not set')
  }

  // Parse da URL para extrair informações
  const url = new URL(connectionUrl)
  const dbName = url.pathname.slice(1) // Remove leading '/'

  // Conecta sem selecionar banco para poder criar se não existir
  const adminPool = new Pool({
    host: url.hostname,
    port: parseInt(url.port) || 5432,
    user: url.username,
    password: url.password,
    database: 'postgres', // Conecta ao banco 'postgres' padrão
    ssl: { rejectUnauthorized: false }, // Ajuste para conexões SSL, se necessário
  })

  const conn = await adminPool.connect()

  try {
    // Criar banco de dados se não existir
    await conn.query(`
      SELECT 1 FROM pg_database 
      WHERE datname = $1
    `, [dbName])
      .then(async (res) => {
        if (res.rows.length === 0) {
          await conn.query(`CREATE DATABASE "${dbName}" ENCODING 'UTF8'`)
          console.log(`Database ${dbName} created`)
        }
      })
      .catch((err) => {
        // Erro ao criar é ok, banco já existe
        if (!err.message.includes('already exists')) {
          console.error('Error creating database:', err)
        }
      })
  } finally {
    conn.release()
    await adminPool.end()
  }

  // Agora conecta ao banco específico
  const appPool = new Pool({ connectionString: connectionUrl })
  const appConn = await appPool.connect()

  try {
    await createTables(appConn)
    await seedDefaultUsers(appConn)
  } finally {
    appConn.release()
    await appPool.end()
  }
}

async function createTables(conn: any): Promise<void> {
  // Criar tipos ENUM (melhor que usar TEXT)
  const enumStatements = [
    `DO $$
    BEGIN
      IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'usuario_tipo_enum') THEN
        CREATE TYPE usuario_tipo_enum AS ENUM ('admin', 'operador', 'supervisor');
      END IF;
    END
    $$;`,

    `DO $$
    BEGIN
      IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'inventario_status_enum') THEN
        CREATE TYPE inventario_status_enum AS ENUM ('planejado', 'aberto', 'em_andamento', 'fechado', 'cancelado');
      END IF;
    END
    $$;`,

    `DO $$
    BEGIN
      IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'inventario_tipo_enum') THEN
        CREATE TYPE inventario_tipo_enum AS ENUM ('fisico', 'ciclico', 'rotativo');
      END IF;
    END
    $$;`,

    `DO $$
    BEGIN
      IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'contagem_status_enum') THEN
        CREATE TYPE contagem_status_enum AS ENUM ('primaria', 'concluida', 'divergente', 'pendente_validacao', 'ajustado', 'terceira', 'secundaria');
      END IF;
    END
    $$;`,
  ]

  for (const stmt of enumStatements) {
    await conn.query(stmt).catch((err) => {
      if (!err.message.includes('already exists')) {
        console.error('Error creating enum:', err.message)
      }
    })
  }

  // Criar tabelas
  const tables: Record<string, string> = {
    usuarios: `CREATE TABLE IF NOT EXISTS usuarios (
      id SERIAL PRIMARY KEY,
      nome VARCHAR(100) NOT NULL,
      matricula VARCHAR(50) UNIQUE NOT NULL,
      email VARCHAR(100),
      tipo usuario_tipo_enum DEFAULT 'operador',
      ativo BOOLEAN DEFAULT TRUE,
      data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      ultimo_login TIMESTAMP NULL,
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )`,

    inventarios: `CREATE TABLE IF NOT EXISTS inventarios (
      id SERIAL PRIMARY KEY,
      codigo VARCHAR(20) UNIQUE NOT NULL,
      data_inicio DATE NOT NULL,
      data_fim DATE NULL,
      descricao VARCHAR(200),
      status inventario_status_enum DEFAULT 'planejado',
      tipo inventario_tipo_enum DEFAULT 'fisico',
      admin_id INT NOT NULL,
      responsavel_id INT NULL,
      data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_inventarios_admin FOREIGN KEY (admin_id) REFERENCES usuarios(id),
      CONSTRAINT fk_inventarios_responsavel FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL
    )`,

    contagens: `CREATE TABLE IF NOT EXISTS contagens (
      id SERIAL PRIMARY KEY,
      inventario_id INT NOT NULL,
      usuario_id INT NOT NULL,
      usuario_secundario_id INT NULL,
      deposito VARCHAR(100) NOT NULL,
      localizacao VARCHAR(50) NULL,
      partnumber VARCHAR(100) NOT NULL,
      descricao_item VARCHAR(200) NULL,
      unidade_medida VARCHAR(20) DEFAULT 'UN',
      lote VARCHAR(50) NULL,
      validade DATE NULL,
      quantidade_primaria DECIMAL(15,4) NOT NULL,
      quantidade_secundaria DECIMAL(15,4) NULL,
      quantidade_sistema DECIMAL(15,4) NULL,
      usuario_terceiro_id INT NULL,
      quantidade_terceira DECIMAL(15,4) NULL,
      quantidade_final DECIMAL(15,4) NULL,
      data_contagem_primaria TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_contagem_secundaria TIMESTAMP NULL,
      data_contagem_terceira TIMESTAMP NULL,
      status contagem_status_enum DEFAULT 'primaria',
      precisa_segunda_contagem BOOLEAN DEFAULT FALSE,
      precisa_terceira_contagem BOOLEAN DEFAULT FALSE,
      motivo_divergencia TEXT NULL,
      ajustado_por INT NULL,
      data_ajuste TIMESTAMP NULL,
      observacoes TEXT NULL,
      finalizado BOOLEAN DEFAULT FALSE,
      numero_contagens_realizadas SMALLINT DEFAULT 1,
      pode_nova_contagem BOOLEAN DEFAULT TRUE,
      data_finalizacao TIMESTAMP NULL,
      UNIQUE (inventario_id, deposito, partnumber, lote),
      CONSTRAINT fk_contagens_inventario FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE,
      CONSTRAINT fk_contagens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
      CONSTRAINT fk_contagens_usuario_secundario FOREIGN KEY (usuario_secundario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
      CONSTRAINT fk_contagens_usuario_terceiro FOREIGN KEY (usuario_terceiro_id) REFERENCES usuarios(id) ON DELETE SET NULL,
      CONSTRAINT fk_contagens_ajustado_por FOREIGN KEY (ajustado_por) REFERENCES usuarios(id) ON DELETE SET NULL
    )`,

    partnumbers_registrados: `CREATE TABLE IF NOT EXISTS partnumbers_registrados (
      id SERIAL PRIMARY KEY,
      partnumber VARCHAR(100) UNIQUE NOT NULL,
      descricao VARCHAR(200),
      unidade_medida VARCHAR(20) DEFAULT 'UN',
      data_primeiro_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_ultimo_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      total_registros INT DEFAULT 1
    )`,

    depositos_registrados: `CREATE TABLE IF NOT EXISTS depositos_registrados (
      id SERIAL PRIMARY KEY,
      deposito VARCHAR(100) UNIQUE NOT NULL,
      localizacao VARCHAR(100),
      data_primeiro_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_ultimo_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      total_registros INT DEFAULT 1
    )`,

    notificacoes_admin: `CREATE TABLE IF NOT EXISTS notificacoes_admin (
      id SERIAL PRIMARY KEY,
      inventario_id INT NOT NULL,
      usuario_nome VARCHAR(100) NOT NULL,
      partnumber VARCHAR(100) NOT NULL,
      deposito VARCHAR(100) NOT NULL,
      fase SMALLINT DEFAULT 1,
      criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )`,

    auditoria: `CREATE TABLE IF NOT EXISTS auditoria (
      id SERIAL PRIMARY KEY,
      usuario_id INT NOT NULL,
      acao VARCHAR(100) NOT NULL,
      modulo VARCHAR(50) NOT NULL,
      dados JSONB NULL,
      ip VARCHAR(45) NULL,
      user_agent TEXT NULL,
      data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )`,

    depositos: `CREATE TABLE IF NOT EXISTS depositos (
      id SERIAL PRIMARY KEY,
      codigo VARCHAR(20) UNIQUE NOT NULL,
      nome VARCHAR(100) NOT NULL,
      descricao VARCHAR(200),
      tipo VARCHAR(50) DEFAULT 'almoxarifado',
      capacidade INT NULL,
      responsavel_id INT NULL,
      ativo BOOLEAN DEFAULT TRUE,
      data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_depositos_responsavel FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL
    )`,

    categorias: `CREATE TABLE IF NOT EXISTS categorias (
      id SERIAL PRIMARY KEY,
      codigo VARCHAR(20) UNIQUE NOT NULL,
      nome VARCHAR(100) NOT NULL,
      descricao TEXT NULL,
      ativo BOOLEAN DEFAULT TRUE,
      data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )`,

    configuracoes: `CREATE TABLE IF NOT EXISTS configuracoes (
      id SERIAL PRIMARY KEY,
      chave VARCHAR(50) UNIQUE NOT NULL,
      valor TEXT NOT NULL,
      descricao VARCHAR(200) NULL,
      tipo VARCHAR(20) DEFAULT 'text',
      categoria VARCHAR(50) DEFAULT 'geral',
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )`,

    bloqueio_login: `CREATE TABLE IF NOT EXISTS bloqueio_login (
      id SERIAL PRIMARY KEY,
      ip VARCHAR(45) UNIQUE NOT NULL,
      tentativas INT DEFAULT 0,
      bloqueado_ate TIMESTAMP NULL,
      data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )`,

    relatorios_agendados: `CREATE TABLE IF NOT EXISTS relatorios_agendados (
      id SERIAL PRIMARY KEY,
      nome VARCHAR(100) NOT NULL,
      tipo VARCHAR(50) NOT NULL,
      parametros JSONB NULL,
      formato VARCHAR(10) DEFAULT 'pdf',
      agendamento VARCHAR(50) NOT NULL,
      email_destino VARCHAR(500) NULL,
      ativo BOOLEAN DEFAULT TRUE,
      ultima_execucao TIMESTAMP NULL,
      proxima_execucao TIMESTAMP NULL,
      usuario_id INT NOT NULL,
      data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_relatorios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )`,

    historico_contagens: `CREATE TABLE IF NOT EXISTS historico_contagens (
      id SERIAL PRIMARY KEY,
      contagem_id INT NOT NULL,
      campo_alterado VARCHAR(50) NOT NULL,
      valor_anterior TEXT NULL,
      valor_novo TEXT NOT NULL,
      usuario_id INT NOT NULL,
      data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      ip VARCHAR(45) NULL,
      user_agent TEXT NULL,
      CONSTRAINT fk_historico_contagem FOREIGN KEY (contagem_id) REFERENCES contagens(id) ON DELETE CASCADE,
      CONSTRAINT fk_historico_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )`,
  }

  for (const [name, sql] of Object.entries(tables)) {
    try {
      await conn.query(sql)
      console.log(`Table ${name} created or already exists`)
    } catch (err: any) {
      console.error(`Error creating table ${name}:`, err.message)
    }
  }

  // Criar índices
  const indexes = [
    'CREATE INDEX IF NOT EXISTS idx_usuarios_tipo_ativo ON usuarios(tipo, ativo)',
    'CREATE INDEX IF NOT EXISTS idx_usuarios_matricula ON usuarios(matricula)',
    'CREATE INDEX IF NOT EXISTS idx_inventarios_status_data ON inventarios(status, data_inicio)',
    'CREATE INDEX IF NOT EXISTS idx_inventarios_codigo ON inventarios(codigo)',
    'CREATE INDEX IF NOT EXISTS idx_contagens_inventario_deposito ON contagens(inventario_id, deposito)',
    'CREATE INDEX IF NOT EXISTS idx_contagens_partnumber_deposito ON contagens(partnumber, deposito)',
    'CREATE INDEX IF NOT EXISTS idx_contagens_status ON contagens(status)',
    'CREATE INDEX IF NOT EXISTS idx_contagens_deposito ON contagens(deposito)',
    'CREATE INDEX IF NOT EXISTS idx_contagens_data ON contagens(data_contagem_primaria)',
    'CREATE INDEX IF NOT EXISTS idx_partnumbers_partnumber ON partnumbers_registrados(partnumber)',
    'CREATE INDEX IF NOT EXISTS idx_depositos_deposito ON depositos_registrados(deposito)',
    'CREATE INDEX IF NOT EXISTS idx_depositos_registros ON depositos_registrados(total_registros, data_ultimo_registro)',
    'CREATE INDEX IF NOT EXISTS idx_notificacoes_inventario_data ON notificacoes_admin(inventario_id, criado_em)',
    'CREATE INDEX IF NOT EXISTS idx_auditoria_usuario_data ON auditoria(usuario_id, data_registro)',
    'CREATE INDEX IF NOT EXISTS idx_auditoria_acao_modulo ON auditoria(acao, modulo)',
    'CREATE INDEX IF NOT EXISTS idx_auditoria_data ON auditoria(data_registro)',
    'CREATE INDEX IF NOT EXISTS idx_relatorios_usuario ON relatorios_agendados(usuario_id)',
    'CREATE INDEX IF NOT EXISTS idx_relatorios_ativo_proximo ON relatorios_agendados(ativo, proxima_execucao)',
    'CREATE INDEX IF NOT EXISTS idx_historico_contagem_data ON historico_contagens(contagem_id, data_alteracao)',
    'CREATE INDEX IF NOT EXISTS idx_historico_usuario ON historico_contagens(usuario_id)',
  ]

  for (const indexSql of indexes) {
    try {
      await conn.query(indexSql)
    } catch (err: any) {
      console.error('Error creating index:', err.message)
    }
  }
}

async function seedDefaultUsers(conn: any): Promise<void> {
  // Inserir usuários padrão se não existirem
  await conn.query(`
    INSERT INTO usuarios (nome, matricula, tipo, email)
    VALUES ($1, $2, $3, $4)
    ON CONFLICT (matricula) DO NOTHING
  `, ['Administrador Sistema', 'admin', 'admin', 'admin@empresa.com'])

  await conn.query(`
    INSERT INTO usuarios (nome, matricula, tipo, email)
    VALUES ($1, $2, $3, $4)
    ON CONFLICT (matricula) DO NOTHING
  `, ['Operador Padrão', 'operador', 'operador', 'operador@empresa.com'])

  console.log('Default users seeded')
}