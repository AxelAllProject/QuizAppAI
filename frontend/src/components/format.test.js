import { describe, expect, it } from 'vitest'
import { toQuery } from './admin'
import { formatPin, ordinal } from './live'
import { formatDuration, hueOf, subjectOf } from './ui'

describe('formatage', () => {
  it('écrit une durée en secondes ou en minutes', () => {
    expect(formatDuration(null)).toBe('—')
    expect(formatDuration(42)).toBe('42 s')
    expect(formatDuration(65)).toBe('1 min 05')
  })

  it('découpe un PIN et écrit les rangs', () => {
    expect(formatPin('123456')).toBe('123 456')
    expect([ordinal(1), ordinal(2)]).toEqual(['1er', '2e'])
  })

  it('donne la même couleur au même texte, quelle que soit la casse', () => {
    expect(hueOf('Axel')).toBe(hueOf('axel'))
    expect(hueOf('Axel')).toBeGreaterThanOrEqual(0)
    expect(hueOf('Axel')).toBeLessThan(360)
  })

  it('devine l’icône d’une matière depuis son nom libre', () => {
    expect(subjectOf('Mathématiques').icon).toBe('calculator')
    expect(subjectOf('Histoire-Géo').icon).toBe('landmark')
    expect(subjectOf('Cuisine').icon).toBe('book')
  })

  it('n’envoie pas les filtres vides au serveur', () => {
    expect(toQuery({ search: 'prof', role: '', status: null })).toBe('?search=prof')
    expect(toQuery({ search: '' })).toBe('')
    expect(toQuery({ search: '100% & co' })).toBe('?search=100%25+%26+co')
  })
})
