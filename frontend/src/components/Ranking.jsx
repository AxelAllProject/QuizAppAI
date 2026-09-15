import { useEffect, useState } from 'react'
import { api } from '../api'
import { useAuth } from '../auth'
import { Medal } from './Icon'
import { Avatar, ErrorBox, formatDate, formatDuration } from './ui'

/**
 * Classement des participants d'un quiz : visible par tout le monde,
 * aussi bien depuis la bibliothèque que juste après une partie.
 * La page parente peut fournir les parties déjà chargées via `sessions`.
 */
export default function Ranking({ quizId, sessions: provided, highlightSessionId }) {
  const { user } = useAuth()
  const [fetched, setFetched] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (provided) return
    api(`/api/quizzes/${quizId}/sessions`).then(setFetched).catch(setError)
  }, [quizId, provided])

  const sessions = provided ?? fetched

  if (error) return <ErrorBox error={error} />

  if (!sessions) return <div className="skeleton" style={{ height: 120 }} />

  if (sessions.length === 0) {
    return <p className="muted">Personne n’a encore joué ce quiz : sois le premier !</p>
  }

  return (
    <div className="table-wrap">
      <table className="ranking">
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
          {sessions.map((session, position) => {
            const mine = session.player === user.name
            const rowClass = session.id === highlightSessionId ? 'current' : mine ? 'mine' : undefined

            return (
              <tr key={session.id} className={rowClass}>
                <td className="muted">{position < 3 ? <Medal rank={position + 1} /> : position + 1}</td>
                <td>
                  <span className="cell-identity">
                    <Avatar name={session.player} size="sm" />
                    {session.player}
                    {mine && <span className="you">toi</span>}
                  </span>
                </td>
                <td>
                  {session.score} / {session.total}
                </td>
                <td>
                  <span className={`badge ${session.accuracy >= 50 ? 'facile' : 'difficile'}`}>
                    {session.accuracy}%
                  </span>
                </td>
                <td className="muted">{formatDuration(session.durationSeconds)}</td>
                <td className="muted">{formatDate(session.playedAt)}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
