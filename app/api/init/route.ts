import { NextResponse } from 'next/server'
import { runMigrations } from '@/lib/migrations'

let migrated = false

export async function GET() {
  if (!migrated) {
    try {
      await runMigrations()
      migrated = true
    } catch (e) {
      console.error('Migration error:', e)
      return NextResponse.json({ error: 'Migration failed' }, { status: 500 })
    }
  }
  return NextResponse.json({ ok: true })
}
