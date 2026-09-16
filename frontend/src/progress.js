/** Seuils partagés par l'accueil, le parcours et les cartes de quiz. */
export const MASTERED = 80
export const TO_REVIEW = 50

/** Meilleur taux de réussite obtenu sur chaque quiz. */
export function bestByQuiz(sessions) {
  const best = new Map()
  for (const session of sessions) {
    if (!session.quizId) continue
    best.set(session.quizId, Math.max(best.get(session.quizId) ?? 0, session.accuracy))
  }
  return best
}

/** Dernière partie de chaque quiz : c'est elle qui dit où en est l'élève aujourd'hui. */
export function latestByQuiz(sessions) {
  const latest = new Map()
  for (const session of sessions) {
    if (!session.quizId) continue
    const current = latest.get(session.quizId)
    if (!current || session.playedAt > current.playedAt) latest.set(session.quizId, session)
  }
  return latest
}

/** Résume la progression : meilleurs scores, moyenne, quiz maîtrisés et à retravailler. */
export function summarize(sessions) {
  const best = bestByQuiz(sessions)

  return {
    best,
    average: sessions.length ? Math.round(sessions.reduce((sum, s) => sum + s.accuracy, 0) / sessions.length) : null,
    mastered: [...best.values()].filter((accuracy) => accuracy >= MASTERED).length,
    toReview: [...latestByQuiz(sessions).values()].filter((session) => session.accuracy < TO_REVIEW),
  }
}

/** Étiquette de progression d'un quiz déjà joué. */
export function progressOf(accuracy) {
  if (accuracy === undefined) return null
  if (accuracy >= MASTERED) return { tone: 'mastered', label: 'Maîtrisé' }
  if (accuracy >= TO_REVIEW) return { tone: 'started', label: 'En progrès' }
  return { tone: 'todo', label: 'À revoir' }
}

/** Couleur associée à un pourcentage de réussite (bon, moyen, faible). */
export function toneOf(accuracy) {
  return accuracy >= MASTERED ? 'good' : accuracy >= TO_REVIEW ? 'mid' : 'low'
}

/** Accorde un mot selon le nombre (« 1 partie », « 2 parties »). */
export function plural(count, word) {
  return `${count} ${word}${count > 1 ? 's' : ''}`
}
