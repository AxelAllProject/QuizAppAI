import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { api, assetUrl } from '../api'
import Confetti from '../components/Confetti'
import Ranking from '../components/Ranking'
import { ErrorBox, Loader, ScoreRing, formatDuration } from '../components/ui'

function verdict(accuracy) {
  if (accuracy === 100) return 'Sans-faute, impressionnant.'
  if (accuracy >= 75) return 'Très bon score, il s’en est fallu de peu.'
  if (accuracy >= 50) return 'La moitié est acquise, une deuxième tentative ?'
  return 'Le sujet mérite une relecture — retente ta chance.'
}

export default function Result() {
  const { id } = useParams()
  const location = useLocation()
  // La partie qu'on vient de jouer est passée par le router : évite un aller-retour réseau.
  const [session, setSession] = useState(location.state?.session ?? null)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (session) return
    api(`/api/sessions/${id}`).then(setSession).catch(setError)
  }, [id, session])

  if (error) {
    return (
      <div className="page">
        <ErrorBox error={error} />
      </div>
    )
  }

  if (!session) {
    return (
      <div className="page play-shell">
        <Loader count={1} />
      </div>
    )
  }

  return (
    <div className="page play-shell">
      {session.accuracy >= 75 && <Confetti />}

      <header className="page-head">
        <div>
          <h1>Résultats</h1>
          <p>{session.quizTitle}</p>
        </div>
        <div className="row">
          {session.quizId && (
            <Link className="btn" to={`/quiz/${session.quizId}`}>
              Rejouer
            </Link>
          )}
          <Link className="btn primary" to="/">
            Bibliothèque
          </Link>
        </div>
      </header>

      <div className="card score-hero" style={{ marginBottom: '1.5rem' }}>
        <ScoreRing accuracy={session.accuracy} />
        <div className="stack" style={{ gap: '0.4rem' }}>
          <h2>
            {session.score} / {session.total} bonnes réponses
          </h2>
          <p style={{ color: 'var(--text-muted)' }}>{verdict(session.accuracy)}</p>
          <div className="row" style={{ marginTop: '0.5rem' }}>
            {session.durationSeconds !== null && (
              <span className="badge">⏱ {formatDuration(session.durationSeconds)}</span>
            )}
            <span className="badge">Joué par {session.player}</span>
          </div>
        </div>
      </div>

      {session.quizId && (
        <>
          <h2 style={{ marginBottom: '0.75rem' }}>Tous les participants</h2>
          <div className="card" style={{ marginBottom: '1.5rem' }}>
            <Ranking quizId={session.quizId} highlightSessionId={session.id} />
          </div>
        </>
      )}

      <h2 style={{ marginBottom: '0.75rem' }}>Correction détaillée</h2>

      <div className="review">
        {session.answers.map((answer, position) => (
          <div key={answer.questionId} className={`review-item ${answer.correct ? 'ok' : 'ko'}`}>
            {answer.image && <img className="thumb" src={assetUrl(answer.image)} alt="" loading="lazy" />}
            <div className="q">
              {position + 1}. {answer.text}
            </div>
            <div className={`line ${answer.correct ? 'good' : 'bad'}`}>
              Ta réponse :{' '}
              <b>
                {answer.chosenIndex === null || answer.chosenIndex === undefined
                  ? 'aucune'
                  : answer.choices[answer.chosenIndex]}
              </b>
            </div>
            {!answer.correct && (
              <div className="line good">
                Bonne réponse : <b>{answer.choices[answer.correctIndex]}</b>
              </div>
            )}
            {answer.explanation && <div className="explain">{answer.explanation}</div>}
          </div>
        ))}
      </div>
    </div>
  )
}
