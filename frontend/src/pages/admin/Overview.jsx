import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../api'
import { SectionHead } from '../../components/admin'
import { ErrorBox, formatDate } from '../../components/ui'

/** Chiffres d'ensemble et raccourcis vers ce qui demande une action. */
export default function Overview() {
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    Promise.all([
      api('/api/stats'),
      api('/api/users'),
      api('/api/access-keys?status=active'),
      api('/api/ai-keys?status=unclaimed'),
      api('/api/sessions?all=1'),
    ])
      .then(([stats, users, accessKeys, aiKeys, sessions]) =>
        setData({ stats, users, accessKeys, aiKeys, sessions: sessions.slice(0, 6) }),
      )
      .catch(setError)
  }, [])

  const { stats, users, accessKeys, aiKeys, sessions } = data ?? {}
  const staff = users?.filter((account) => account.role !== 'user').length

  const tiles = [
    { label: 'Comptes', value: users?.length, to: '/admin/comptes', hint: `dont ${staff ?? '—'} encadrants` },
    { label: 'Quiz publiés', value: stats?.quizCount, to: '/', hint: 'dans la bibliothèque' },
    { label: 'Parties jouées', value: stats?.sessionCount, to: '/admin/parties', hint: 'solo et en direct' },
    {
      label: 'Réussite globale',
      value: stats ? `${stats.globalAccuracy}%` : undefined,
      to: '/admin/parties',
      hint: 'toutes parties confondues',
    },
    { label: 'Clés d’accès actives', value: accessKeys?.length, to: '/admin/cles', hint: 'encore utilisables' },
    { label: 'Clés IA à distribuer', value: aiKeys?.length, to: '/admin/cles-ia', hint: 'émises, pas encore liées' },
  ]

  return (
    <>
      <SectionHead
        title="Vue d’ensemble"
        action={
          <Link className="btn primary" to="/create">
            Créer un quiz
          </Link>
        }
      >
        L’état de la plateforme en un écran : contenus, comptes, droits accordés et activité récente.
      </SectionHead>

      <ErrorBox error={error} />

      <div className="metric-grid">
        {tiles.map(({ label, value, to, hint }) => (
          <Link className="metric" key={label} to={to}>
            <span className="metric-label">{label}</span>
            <span className="metric-value">{value ?? '—'}</span>
            <span className="metric-hint">{hint}</span>
          </Link>
        ))}
      </div>

      <div className="admin-columns">
        <section className="panel">
          <header className="panel-head">
            <h2>Meilleurs joueurs</h2>
            <Link className="btn ghost sm" to="/admin/parties">
              Tout voir
            </Link>
          </header>
          {stats?.leaderboard?.length ? (
            <ol className="rank-list">
              {stats.leaderboard.slice(0, 5).map((row, position) => (
                <li key={row.player}>
                  <span className="rank-position">{position + 1}</span>
                  <span className="rank-name">{row.player}</span>
                  <span className="status status-ok">{row.accuracy}%</span>
                </li>
              ))}
            </ol>
          ) : (
            <p className="table-empty">Aucune partie enregistrée pour l’instant.</p>
          )}
        </section>

        <section className="panel">
          <header className="panel-head">
            <h2>Dernières parties</h2>
            <Link className="btn ghost sm" to="/admin/parties">
              Tout voir
            </Link>
          </header>
          {sessions?.length ? (
            <ul className="feed">
              {sessions.map((session) => (
                <li key={session.id}>
                  <Link to={`/session/${session.id}`}>
                    <b>{session.player}</b> — {session.quizTitle}
                    <small>
                      {session.score} / {session.total} · {formatDate(session.playedAt)}
                    </small>
                  </Link>
                </li>
              ))}
            </ul>
          ) : (
            <p className="table-empty">Aucune partie enregistrée pour l’instant.</p>
          )}
        </section>
      </div>
    </>
  )
}
