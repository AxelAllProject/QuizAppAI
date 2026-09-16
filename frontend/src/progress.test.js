import { describe, expect, it } from 'vitest'
import { bestByQuiz, latestByQuiz, plural, progressOf, summarize, toneOf } from './progress'

const session = (quizId, accuracy, playedAt) => ({ quizId, accuracy, playedAt })

describe('progression du joueur', () => {
  it('garde le meilleur score de chaque quiz et ignore les quiz supprimés', () => {
    const best = bestByQuiz([session(1, 40, '2026-09-01'), session(1, 90, '2026-09-02'), session(null, 100, '2026-09-03')])

    expect([...best]).toEqual([[1, 90]])
  })

  it('retient la dernière partie de chaque quiz, pas la meilleure', () => {
    const latest = latestByQuiz([session(1, 90, '2026-09-01T10:00:00+00:00'), session(1, 20, '2026-09-02T10:00:00+00:00')])

    expect(latest.get(1).accuracy).toBe(20)
  })

  it('résume moyenne, quiz maîtrisés et quiz à revoir', () => {
    const summary = summarize([
      session(1, 100, '2026-09-01'),
      session(2, 30, '2026-09-01'),
      session(2, 45, '2026-09-02'),
    ])

    expect(summary.average).toBe(58)
    expect(summary.mastered).toBe(1)
    expect(summary.toReview.map((s) => s.quizId)).toEqual([2])
  })

  it('n’affiche pas de moyenne sans partie', () => {
    expect(summarize([]).average).toBeNull()
  })

  it('classe un score selon les seuils partagés (80 % et 50 %)', () => {
    expect(progressOf(undefined)).toBeNull()
    expect(progressOf(80).tone).toBe('mastered')
    expect(progressOf(50).tone).toBe('started')
    expect(progressOf(49).tone).toBe('todo')
    expect([toneOf(80), toneOf(79), toneOf(49)]).toEqual(['good', 'mid', 'low'])
  })

  it('accorde les mots au pluriel', () => {
    expect([plural(0, 'partie'), plural(1, 'partie'), plural(2, 'partie')]).toEqual(['0 partie', '1 partie', '2 parties'])
  })
})
