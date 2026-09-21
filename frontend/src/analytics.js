import { api } from './api'

/**
 * Mesure d'usage, première partie : les événements partent vers notre propre API, jamais
 * vers un service tiers. Aucun cookie, aucun identifiant de navigateur, aucune adresse IP
 * conservée — le serveur ne retient que l'écran, sa durée et, si la personne est connectée,
 * son compte (voir backend/src/Analytics).
 *
 * Les actions qui comptent vraiment (quiz créé, partie lancée, génération IA) sont mesurées
 * côté serveur : elles ne dépendent pas de ce fichier, qu'un bloqueur de scripts peut très
 * bien empêcher de tourner. Ici on ne déclare que ce que le navigateur est seul à savoir.
 */

/** Une mesure perdue n'est qu'une mesure perdue : elle ne doit jamais remonter à l'écran. */
function send(body, keepalive = false) {
  api('/api/events', { method: 'POST', body, keepalive }).catch(() => {})
}

/** Déclare l'ouverture d'un quiz : comparée aux quiz terminés, elle donne le taux d'abandon. */
export function trackQuizStarted(quizId) {
  send({ name: 'quiz_started', subject: String(quizId) })
}

/**
 * Déclare la page quittée et le temps passé dessus. Appelée au changement d'adresse, donc
 * toujours pour l'écran **précédent** : c'est en le quittant qu'on connaît sa durée.
 *
 * `closing` marque le départ du site (onglet fermé, navigation ailleurs) : la requête doit
 * alors survivre à la page, sans quoi le dernier écran de chaque visite ne serait jamais compté.
 */
export function trackPageView(path, openedAt, closing = false) {
  send({ name: 'page_view', path, durationMs: Math.round(performance.now() - openedAt) }, closing)
}
