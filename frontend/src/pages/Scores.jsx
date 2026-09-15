import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api'
import Ranking from '../components/Ranking'
import { Avatar, ErrorBox, formatDuration } from '../components/ui'
import { Icon } from '../components/Icon'

export default function Scores() {
  const { id } = useParams()
  const [quiz, setQuiz] = useState(null)
  const [sessions, setSessions] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api(`/api/quizzes/${id}`).then(setQuiz).catch(setError)
    api(`/api/quizzes/${id}/sessions`).then(setSessions).catch(setError)
  }, [id])

  // Le classement est déjà trié : la première partie de chaque joueur est sa meilleure.
  const podium = useMemo(() => {
    const seen = new Set()
    return (sessions ?? []).filter((session) => !seen.has(session.player) && seen.add(session.player)).slice(0, 3)
  }, [sessions])

  return (
    <div className="page play-shell">
      <header className="page-head">
        <div>
          <span className="eyebrow"><Icon name="trophy" size={14} /> Classement de la classe</span>
          <h1 style={{ marginTop: '0.4rem' }}>{quiz?.title ?? 'Chargement…'}</h1>
          <p>Au taux de bonnes réponses, puis au temps : à toi de grimper sur le podium.</p>
        </div>
        <div className="row">
          <Link className="btn primary" to={`/quiz/${id}`}>
            Relever le défi
          </Link>
          <Link className="btn ghost" to="/">
            Accueil
          </Link>
        </div>
      </header>

      <ErrorBox error={error} />

      {podium.length > 0 && (
        <div className="podium" style={{ marginBottom: '1.5rem' }}>
          {[
            [podium[1], 2],
            [podium[0], 1],
            [podium[2], 3],
          ]
            .filter(([session]) => session)
            .map(([session, rank]) => (
              <div className={`step p${rank}`} key={rank}>
                <Avatar name={session.player} size="lg" />
                <div className="name">{session.player}</div>
                <div className="score">
                  {session.accuracy}% · {formatDuration(session.durationSeconds)}
                </div>
                <div className="block">{rank}</div>
              </div>
            ))}
        </div>
      )}

      <div className="card">
        <Ranking quizId={id} sessions={sessions ?? undefined} />
      </div>
    </div>
  )
}
