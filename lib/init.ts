// lib/init-postgres.ts
// Este arquivo substitui lib/init.ts quando usar PostgreSQL

import { runMigrations } from './migrations'

/**
 * Inicializa o banco de dados PostgreSQL
 * Deve ser chamado uma vez ao iniciar a aplicação
 */
export async function initDatabase(): Promise<void> {
  try {
    console.log('🔄 Inicializando banco de dados PostgreSQL...')
    await runMigrations()
    console.log('✅ Banco de dados inicializado com sucesso!')
  } catch (error) {
    console.error('❌ Erro ao inicializar banco de dados:', error)
    throw error
  }
}

/**
 * Função auxiliar para fechar a conexão (opcional)
 * Útil ao finalizar a aplicação
 */
export async function closeDatabase(): Promise<void> {
  try {
    const { closePool } = await import('./db')
    await closePool()
    console.log('✅ Conexão com banco de dados fechada')
  } catch (error) {
    console.error('❌ Erro ao fechar banco de dados:', error)
  }
}