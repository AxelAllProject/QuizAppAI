import { useEffect, useState } from 'react'
import { api } from '../api'
import { ErrorBox, formatDate, formatDuration } from './ui'

/**
 * Classement des participants d'un quiz : visible par tout le monde,
 * aussi bien depuis la bibliothèque que juste après une partie.
 */
export default function Ranking({ quizId, highlightSessionId }) {
  const [sessions, setSessions] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api(`/api/quizzes/${quizId}/sessions`).then(setSessions).catch(setError)
  }, [quizId])

  if (error) return <ErrorBox error={error} />

  if (!sessions) return <div className="skeleton" style={{ height: 120 }} />

  if (sessions.length === 0) {
    return <p style={{ color: 'var(--text-muted)' }}>Personne n’a encore joué ce quiz.</p>
  }

  return (
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Joueur</th>
          <th>Score</th>
          <th>Réussite</th>
          <th>Durée</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        {sessions.map((session, position) => (
          <tr
            key={session.id}
            style={session.id === highlightSessionId ? { background: 'var(--accent-soft)' } : undefined}
          >
            <td style={{ color: 'var(--text-faint)' }}>{position + 1}</td>
            <td>{session.player}</td>
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
          </tr>
        ))}
      </tbody>
    </table>
  )
}
