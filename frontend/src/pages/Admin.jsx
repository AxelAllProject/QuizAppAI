import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { useAuth } from '../auth'
import AccessKeys from '../components/AccessKeys'
import AiKeys from '../components/AiKeys'
import { ErrorBox, formatDate } from '../components/ui'

export default function Admin() {
  const { user } = useAuth()
  const [stats, setStats] = useState(null)
  const [sessions, setSessions] = useState([])
  const [users, setUsers] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    Promise.all([api('/api/stats'), api('/api/sessions?all=1'), api('/api/users')])
      .then(([stats, sessions, users]) => {
        setStats(stats)
        setSessions(sessions)
        setUsers(users)
      })
      .catch(setError)
  }, [])

  async function changeRole(account, role) {
    setError(null)
    try {
      const updated = await api(`/api/users/${account.id}/role`, { method: 'PUT', body: { role } })
      setUsers((current) => current.map((item) => (item.id === account.id ? { ...item, ...updated, games: item.games, accuracy: item.accuracy } : item)))
    } catch (err) {
      setError(err)
    }
  }

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <h1>Tableau de bord</h1>
          <p>Vue d’ensemble de la plateforme : contenus, comptes, parties et classement.</p>
        </div>
        <Link className="btn primary" to="/create">
          + Créer un quiz
        </Link>
      </header>

      <ErrorBox error={error} />

      <div className="stat-grid" style={{ marginBottom: '1.5rem' }}>
        {[
          ['Quiz publiés', stats?.quizCount],
          ['Parties jouées', stats?.sessionCount],
          ['Comptes', users.length || null],
          ['Réussite globale', stats ? `${stats.globalAccuracy}%` : null],
        ].map(([label, value]) => (
          <div className="card stat" key={label}>
            <div className="value">{value ?? '—'}</div>
            <div className="label">{label}</div>
          </div>
        ))}
      </div>

      <h2 style={{ marginBottom: '0.75rem' }}>Clés d’accès</h2>
      <p style={{ color: 'var(--text-muted)', marginBottom: '0.75rem', fontSize: '0.9rem' }}>
        Une clé professeur autorise la rédaction de quiz et l’animation de parties en direct ; une clé
        administrateur donne en plus accès à cet écran. Choisis un compte existant pour lui donner le rôle
        tout de suite, ou laisse le champ vide pour générer un code que la personne saisira elle-même
        (à l’inscription ou dans « Mon compte »). Pour retirer un rôle déjà accordé, change-le ci-dessous.
      </p>
      <AccessKeys users={users} />

      <h2 style={{ marginBottom: '0.75rem' }}>Clés IA</h2>
      <p style={{ color: 'var(--text-muted)', marginBottom: '0.75rem', fontSize: '0.9rem' }}>
        La génération de quiz par IA est une fonctionnalité <b>premium</b> : avoir le rôle professeur ou
        administrateur ne suffit pas, il faut en plus détenir une clé IA active. Chaque clé se lie à un
        compte — directement si tu le choisis ci-dessous, ou au premier qui saisit le code sinon — et donne
        droit à un nombre fixe de générations, avec une expiration facultative.
      </p>
      <AiKeys users={users} />

      <h2 style={{ marginBottom: '0.75rem' }}>Comptes</h2>
      <div className="card" style={{ marginBottom: '1.5rem', overflowX: 'auto' }}>
        {users.length ? (
          <table>
            <thead>
              <tr>
                <th>Pseudo</th>
                <th>Rôle</th>
                <th>Parties</th>
                <th>Réussite</th>
                <th>Inscrit le</th>
                <th>Dernière connexion</th>
              </tr>
            </thead>
            <tbody>
              {users.map((account) => (
                <tr key={account.id}>
                  <td>{account.name}</td>
                  <td>
                    <select
                      value={account.role}
                      onChange={(e) => changeRole(account, e.target.value)}
                      disabled={account.id === user.id}
                      aria-label={`Rôle de ${account.name}`}
                      style={{ width: 'auto', padding: '0.3rem 0.5rem', fontSize: '0.82rem' }}
                    >
                      <option value="user">joueur</option>
                      <option value="prof">professeur</option>
                      <option value="admin">administrateur</option>
                    </select>
                  </td>
                  <td>{account.games}</td>
                  <td style={{ color: 'var(--text-muted)' }}>
                    {account.accuracy === null ? '—' : `${account.accuracy}%`}
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>{formatDate(account.createdAt)}</td>
                  <td style={{ color: 'var(--text-muted)' }}>{formatDate(account.lastSeenAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p style={{ color: 'var(--text-muted)' }}>Aucun compte pour l’instant.</p>
        )}
      </div>

      <h2 style={{ marginBottom: '0.75rem' }}>Classement</h2>
      <div className="card" style={{ marginBottom: '1.5rem' }}>
        {stats?.leaderboard?.length ? (
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Joueur</th>
                <th>Parties</th>
                <th>Bonnes réponses</th>
                <th>Réussite</th>
              </tr>
            </thead>
            <tbody>
              {stats.leaderboard.map((row, position) => (
                <tr key={row.player}>
                  <td style={{ color: 'var(--text-faint)' }}>{position + 1}</td>
                  <td>{row.player}</td>
                  <td>{row.games}</td>
                  <td>
                    {row.score} / {row.total}
                  </td>
                  <td>
                    <span className="badge accent">{row.accuracy}%</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p style={{ color: 'var(--text-muted)' }}>Aucune partie enregistrée pour l’instant.</p>
        )}
      </div>

      <h2 style={{ marginBottom: '0.75rem' }}>Dernières parties</h2>
      <div className="card">
        {sessions.length ? (
          <table>
            <thead>
              <tr>
                <th>Joueur</th>
                <th>Quiz</th>
                <th>Score</th>
                <th>Date</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {sessions.map((session) => (
                <tr key={session.id}>
                  <td>{session.player}</td>
                  <td>{session.quizTitle}</td>
                  <td>
                    {session.score} / {session.total}
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>{formatDate(session.playedAt)}</td>
                  <td style={{ textAlign: 'right' }}>
                    <Link className="btn ghost sm" to={`/session/${session.id}`}>
                      Détail
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p style={{ color: 'var(--text-muted)' }}>Aucune partie enregistrée pour l’instant.</p>
        )}
      </div>
    </div>
  )
}
