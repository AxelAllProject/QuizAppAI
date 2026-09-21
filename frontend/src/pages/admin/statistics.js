/** Calculs de la page Statistiques, isolés de l'affichage pour être testables. */

/**
 * Met en forme la série renvoyée par l'API pour la courbe.
 *
 * Le serveur envoie déjà un point par jour, zéros compris : il est seul à connaître le
 * fuseau dans lequel les mesures ont été datées, et reconstruire les dates ici décalerait
 * la courbe d'un jour dès que le navigateur n'est pas dans le même fuseau que le serveur.
 *
 * @param series liste de { date: 'AAAA-MM-JJ', value: nombre }
 */
export function toSeries(series) {
  return series.map(({ date, value }) => ({
    value,
    // Date construite à midi : à minuit, un décalage horaire d'une heure suffirait à
    // afficher la veille.
    label: new Date(`${date}T12:00:00`).toLocaleDateString('fr-FR', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    }),
  }))
}

/** Durée moyenne passée sur un écran, en secondes ou en minutes. */
export function formatDuration(ms) {
  if (!ms) return '—'

  const seconds = Math.round(ms / 1000)

  return seconds < 60 ? `${seconds} s` : `${Math.floor(seconds / 60)} min ${String(seconds % 60).padStart(2, '0')}`
}
