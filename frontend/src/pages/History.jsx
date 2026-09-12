import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { Empty, ErrorBox, formatDate, formatDuration } from '../components/ui'

export default function History() {
  const [sessions, setSessions] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api('/api/sessions').then(setSessions).catch(setError)
  }, [])

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <h1>Mes parties</h1>
          <p>Chaque session est conservée avec sa correction complète.</p>
        </div>
      </header>

      <ErrorBox error={error} />

      {sessions?.length === 0 ? (
        <Empty
          title="Aucune partie pour le moment"
          action={
            <Link className="btn primary" to="/">
              Choisir un quiz
            </Link>
          }
        >
          Joue un premier quiz pour voir tes résultats s’afficher ici.
        </Empty>
      ) : (
        <div className="card">
          <table>
            <thead>
              <tr>
                <th>Quiz</th>
                <th>Score</th>
                <th>Réussite</th>
                <th>Durée</th>
                <th>Date</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {(sessions ?? []).map((session) => (
                <tr key={session.id}>
                  <td>{session.quizTitle}</td>
                  <td>
                    {session.score} / {session.total}
                  </td>
                  <td>
                    <span className={`badge ${session.accuracy >= 50 ? 'facile' : 'difficile'}`}>
                      {session.accuracy}%
                    </span>
                  </td>
                  <td style={{ color: 'var(--text-muted)' }}>{formatDuration(session.durationSeconds)}</td>
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
        </div>
      )}
    </div>
  )
}
