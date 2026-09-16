import { afterEach, describe, expect, it, vi } from 'vitest'
import { uid } from './uid'

describe('uid', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('utilise crypto.randomUUID quand le contexte est sécurisé', () => {
    vi.stubGlobal('crypto', { randomUUID: () => 'uuid-fixe' })

    expect(uid()).toBe('uuid-fixe')
  })

  it('reste unique sans crypto.randomUUID (page ouverte en http depuis le réseau local)', () => {
    vi.stubGlobal('crypto', {})

    const ids = new Set(Array.from({ length: 1000 }, uid))

    expect(ids.size).toBe(1000)
  })
})
