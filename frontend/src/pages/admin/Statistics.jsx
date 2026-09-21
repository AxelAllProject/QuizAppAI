import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../api'
import { SectionHead } from '../../components/admin'
import Sparkline from '../../components/Sparkline'
import { ErrorBox, Loader, Subject } from '../../components/ui'
import { formatDuration, toSeries } from './statistics'

/** Fenêtres proposées, alignées sur celles qu'accepte l'API. */
const WINDOWS = [
  { days: 7, label: '7 jours' },
  { days: 30, label: '30 jours' },
  { days: 90, label: '3 mois' },
  { days: 365, label: '1 an' },
]

/** Libellé lisible de chaque rôle dans la répartition d'audience. */
const ROLE_LABELS = { user: 'élèves', prof: 'professeurs', admin: 'administrateurs', anonyme: 'visiteurs' }

/** Écrans de l'application, nommés comme on en parle (les chemins sont normalisés côté serveur). */
const PAGE_LABELS = {
  '/': 'Accueil et bibliothèque',
  '/quiz/:id': 'Un quiz (partie solo)',
  '/quiz/:id/edit': 'Éditeur de quiz',
  '/quiz/:id/scores': 'Classement d’un quiz',
  '/session/:id': 'Correction d’une partie',
  '/history': 'Mon parcours',
  '/communaute': 'Communauté',
  '/create': 'Créer un quiz',
  '/join': 'Rejoindre une partie',
  '/live/:id': 'Partie en direct (joueur)',
  '/live/:id/host': 'Partie en direct (animateur)',
  '/account': 'Mon compte',
  '/aide': 'Aide',
  '/login': 'Connexion',
  '/confidentialite': 'Confidentialité',
}

/** Mesure d'usage : audience, écrans consultés, actions clés et usage pédagogique. */
export default function Statistics() {
  const [days, setDays] = useState(30)
  const [report, setReport] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    let cancelled = false

    api(`/api/analytics?days=${days}`)
      .then((data) => {
        if (cancelled) return
        setReport(data)
        setError(null)
      })
      .catch((err) => !cancelled && setError(err))

    return () => {
      cancelled = true
    }
  }, [days])

  const { audience, pages, actions, ai, learning } = report ?? {}

  return (
    <>
      <SectionHead title="Statistiques">
        Qui vient, sur quels écrans, et ce qui est réellement utilisé. Mesuré par QuizLab lui-même :
        aucune donnée n’est envoyée à un service tiers, et rien n’est déposé dans le navigateur.{' '}
        <Link className="link" to="/confidentialite">
          En savoir plus
        </Link>
        .
      </SectionHead>

      <ErrorBox error={error} />

      {/* Un seul filtre, au-dessus de tout ce qu'il pilote. */}
      <div className="tabs" role="tablist" style={{ marginBottom: '1.5rem', width: 'fit-content' }}>
        {WINDOWS.map((window) => (
          <button
            key={window.days}
            type="button"
            role="tab"
            aria-selected={days === window.days}
            className={days === window.days ? 'active' : ''}
            onClick={() => setDays(window.days)}
          >
            {window.label}
          </button>
        ))}
      </div>

      {!report ? (
        <Loader count={2} />
      ) : (
        <>
          <div className="metric-grid">
            <Tile label="Actifs aujourd’hui" value={audience.activeToday} hint="comptes ayant agi depuis minuit" />
            <Tile label="Actifs sur 7 jours" value={audience.activeLast7Days} hint="comptes distincts" />
            <Tile label="Actifs sur 30 jours" value={audience.activeLast30Days} hint="comptes distincts" />
            <Tile label="Nouveaux comptes" value={audience.signups} hint={`sur ${days} jours`} />
            <Tile
              label="Réussite de l’IA"
              value={ai.successRate === null ? '—' : `${ai.successRate}%`}
              hint={ai.attempted > 0 ? `${ai.succeeded} / ${ai.attempted} générations` : 'aucune génération tentée'}
            />
            <Tile
              label="Abandon des quiz"
              value={learning.abandonRate === null ? '—' : `${learning.abandonRate}%`}
              hint="ouverts sans être terminés"
            />
          </div>

          {/* Deux mesures d'ordres de grandeur différents : deux cadres, jamais deux axes. */}
          <div className="admin-columns" style={{ marginBottom: '1rem' }}>
            <section className="panel">
              <header className="panel-head">
                <h2>Pages vues par jour</h2>
              </header>
              <div className="panel-body">
                <Sparkline points={toSeries(audience.dailyPageViews)} label="Pages vues par jour" />
              </div>
            </section>

            <section className="panel">
              <header className="panel-head">
                <h2>Comptes actifs par jour</h2>
              </header>
              <div className="panel-body">
                <Sparkline points={toSeries(audience.dailyActive)} label="Comptes actifs par jour" />
              </div>
            </section>
          </div>

          <div className="admin-columns">
            <section className="panel">
              <header className="panel-head">
                <h2>Écrans les plus consultés</h2>
                <span className="muted">durée moyenne</span>
              </header>
              {pages.length ? (
                <ol className="rank-list">
                  {pages.map((page, position) => (
                    <li key={page.path}>
                      <span className="rank-position">{position + 1}</span>
                      <span className="rank-name">
                        {PAGE_LABELS[page.path] ?? page.path}
                        <small className="muted"> · {page.views} vues, {page.visitors} comptes</small>
                      </span>
                      <span className="muted">{formatDuration(page.averageMs)}</span>
                    </li>
                  ))}
                </ol>
              ) : (
                <p className="table-empty">Aucune page vue sur cette période.</p>
              )}
            </section>

            <section className="panel">
              <header className="panel-head">
                <h2>Actions clés</h2>
                <span className="muted">sur {days} jours</span>
              </header>
              <ul className="rank-list">
                <Action label="Connexions" value={actions.logins} />
                <Action label="Quiz créés" value={actions.quizzesCreated} />
                <Action label="Quiz lancés" value={actions.quizzesStarted} />
                <Action label="Quiz terminés" value={actions.quizzesFinished} />
                <Action label="Parties en direct animées" value={actions.liveGamesHosted} />
                <Action label="Joueurs entrés en direct" value={actions.liveGamesJoined} />
                <Action label="Exports RGPD demandés" value={actions.rgpdExports} />
              </ul>
            </section>

            <section className="panel">
              <header className="panel-head">
                <h2>Quiz les plus joués</h2>
                <span className="muted">{learning.neverPlayedQuizzes} jamais joué(s)</span>
              </header>
              {learning.topQuizzes.length ? (
                <ol className="rank-list">
                  {learning.topQuizzes.map((quiz, position) => (
                    <li key={quiz.id}>
                      <span className="rank-position">{position + 1}</span>
                      <Link className="rank-name" to={`/quiz/${quiz.id}/scores`}>
                        {quiz.title}
                      </Link>
                      <span className="muted">{quiz.plays} parties</span>
                    </li>
                  ))}
                </ol>
              ) : (
                <p className="table-empty">Aucune partie terminée sur cette période.</p>
              )}
            </section>

            <section className="panel">
              <header className="panel-head">
                <h2>Qui consulte QuizLab</h2>
                <span className="muted">pages vues</span>
              </header>
              {Object.keys(audience.byRole).length ? (
                <ul className="rank-list">
                  {Object.entries(audience.byRole)
                    .sort(([, a], [, b]) => b - a)
                    .map(([role, views]) => (
                      <li key={role}>
                        <span className="rank-name">{ROLE_LABELS[role] ?? role}</span>
                        <span className="muted">{views} vues</span>
                      </li>
                    ))}
                </ul>
              ) : (
                <p className="table-empty">Aucune page vue sur cette période.</p>
              )}
            </section>

            <section className="panel">
              <header className="panel-head">
                <h2>Matières les plus travaillées</h2>
              </header>
              {Object.keys(learning.topCategories).length ? (
                <ul className="rank-list">
                  {Object.entries(learning.topCategories).map(([category, plays]) => (
                    <li key={category}>
                      <span className="rank-name">
                        <Subject category={category} />
                      </span>
                      <span className="muted">{plays} parties</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="table-empty">Aucune partie terminée sur cette période.</p>
              )}
            </section>
          </div>

          <p className="hint" style={{ marginTop: '1rem' }}>
            Les mesures sont conservées un an, puis supprimées par <code>app:rgpd:purge</code>. Elles ne
            contiennent ni adresse IP, ni empreinte de navigateur, et le lien vers un compte est coupé dès
            que celui-ci est supprimé.
          </p>
        </>
      )}
    </>
  )
}

/** Un chiffre d'ensemble. */
function Tile({ label, value, hint }) {
  return (
    <div className="metric">
      <span className="metric-label">{label}</span>
      <span className="metric-value">{value ?? '—'}</span>
      <span className="metric-hint">{hint}</span>
    </div>
  )
}

/** Une ligne du décompte des actions clés. */
function Action({ label, value }) {
  return (
    <li>
      <span className="rank-name">{label}</span>
      <span className="muted">{value}</span>
    </li>
  )
}
