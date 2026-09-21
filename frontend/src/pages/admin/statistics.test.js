import { describe, expect, it } from 'vitest'
import { formatDuration, toSeries } from './statistics'

describe('toSeries', () => {
  it('garde un point par jour, zéros compris', () => {
    const points = toSeries([
      { date: '2026-09-19', value: 0 },
      { date: '2026-09-20', value: 12 },
      { date: '2026-09-21', value: 3 },
    ])

    expect(points.map((point) => point.value)).toEqual([0, 12, 3])
  })

  /** Une date lue à minuit basculerait sur la veille dans les fuseaux en retard sur UTC. */
  it('date les points à midi, pour ne pas afficher la veille', () => {
    const [point] = toSeries([{ date: '2026-09-21', value: 1 }])

    expect(point.label).toContain('21')
  })

  it('accepte une série vide', () => {
    expect(toSeries([])).toEqual([])
  })
})

describe('formatDuration', () => {
  it('affiche les courtes durées en secondes', () => {
    expect(formatDuration(45_000)).toBe('45 s')
  })

  it('passe en minutes au-delà d’une minute', () => {
    expect(formatDuration(135_000)).toBe('2 min 15')
  })

  it('complète les secondes sur deux chiffres', () => {
    expect(formatDuration(65_000)).toBe('1 min 05')
  })

  /** Un écran jamais mesuré n'a pas une durée de zéro : il n'a pas de durée. */
  it('n’invente pas de durée quand il n’y en a pas', () => {
    expect(formatDuration(0)).toBe('—')
    expect(formatDuration(null)).toBe('—')
  })
})
