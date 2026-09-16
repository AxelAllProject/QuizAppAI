import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { api, assetUrl } from '../api'
import { useAuth } from '../auth'
import Confetti from '../components/Confetti'
import Ranking from '../components/Ranking'
import { ErrorBox, Loader, ScoreRing, formatDuration } from '../components/ui'
import { plural } from '../progress'
import { Icon } from '../components/Icon'

/** Message d'encouragement selon le pourcentage de réussite. */
function verdict(accuracy) {
  if (accuracy === 100) return 'Sans-faute ! Tu maîtrises ce sujet — de quoi lancer le défi à tes camarades.'
  if (accuracy >= 75) return 'Très bien joué : il reste quelques notions à consolider, détaillées ci-dessous.'
  if (accuracy >= 50) return 'L’essentiel est acquis. Relis les corrections, puis retente ta chance.'
  return 'Chaque erreur est une occasion d’apprendre : les explications ci-dessous vont t’aider.'
}

/** Écrit une place au classement (1re, 2e…). */
function place(rank) {
  return rank === 1 ? '1re' : `${rank}e`
}

/** Correction d'une question : réponse donnée, bonne réponse et explication. */
function Lesson({ answer, position }) {
  const chosen = answer.chosenIndex === null || answer.chosenIndex === undefined ? null : answer.choices[answer.chosenIndex]

  return (
    <article className={`lesson ${answer.correct ? 'ok' : ''}`}>
      {answer.image && <img className="thumb" src={assetUrl(answer.image)} alt="" loading="lazy" />}
      <div className="q">
        <small>Question {position + 1}</small>
        {answer.text}
      </div>
      {!answer.correct && (
        <div className="answer wrong">
          <Icon name="x" size={16} strokeWidth={2.5} />
          <span>
            Ta réponse : <b>{chosen ?? 'aucune'}</b>
          </span>
        </div>
      )}
      <div className="answer right">
        <Icon name="check" size={16} strokeWidth={2.5} />
        <span>
          Bonne réponse : <b>{answer.choices[answer.correctIndex]}</b>
        </span>
      </div>
      {answer.explanation && (
        <div className="tip">
          <Icon name="lightbulb" size={16} /> <b>Pour retenir :</b> {answer.explanation}
        </div>
      )}
    </article>
  )
}

/** Page de correction d'une partie, avec le classement du quiz. */
export default function Result() {
  const { id } = useParams()
  const location = useLocation()
  const { user } = useAuth()
  // La partie qu'on vient de jouer est passée par le router : évite un aller-retour réseau.
  const [session, setSession] = useState(location.state?.session ?? null)
  const [ranking, setRanking] = useState(null)
  const [history, setHistory] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    if (session) return
    api(`/api/sessions/${id}`).then(setSession).catch(setError)
  }, [id, session])

  const quizId = session?.quizId
  useEffect(() => {
    if (!quizId) return
    api(`/api/quizzes/${quizId}/sessions`).then(setRanking).catch(() => setRanking([]))
    api('/api/sessions').then(setHistory).catch(() => {})
  }, [quizId])

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

  const rank = ranking ? ranking.findIndex((row) => row.id === session.id) + 1 : 0
  // La comparaison n'a de sens que sur ses propres parties (un admin peut ouvrir celle d'un autre).
  const previous =
    session.player === user.name
      ? history
          .filter((row) => row.quizId === session.quizId && row.id !== session.id && row.playedAt <= session.playedAt)
          .sort((a, b) => b.playedAt.localeCompare(a.playedAt))[0]
      : undefined
  const delta = previous ? session.accuracy - previous.accuracy : null

  const lessons = session.answers.map((answer, position) => ({ answer, position }))
  const mistakes = lessons.filter(({ answer }) => !answer.correct)
  const successes = lessons.filter(({ answer }) => answer.correct)

  return (
    <div className="page play-shell">
      {session.accuracy >= 75 && <Confetti />}

      <header className="page-head">
        <div>
          <span className="eyebrow"><Icon name="clipboard" size={14} /> Bilan de ta partie</span>
          <h1 style={{ marginTop: '0.4rem' }}>{session.quizTitle}</h1>
        </div>
        <div className="row">
          {session.quizId && (
            <Link className="btn" to={`/quiz/${session.quizId}`}>
              Rejouer
            </Link>
          )}
          <Link className="btn primary" to="/">
            Accueil
          </Link>
        </div>
      </header>

      <div className="card score-hero" style={{ marginBottom: '1.75rem' }}>
        <ScoreRing accuracy={session.accuracy} />
        <div className="stack" style={{ gap: '0.4rem', flex: '1 1 260px' }}>
          <h2>
            {session.score} / {session.total} bonnes réponses
          </h2>
          <p className="muted">{verdict(session.accuracy)}</p>
          <div className="row" style={{ marginTop: '0.35rem' }}>
            {session.durationSeconds !== null && (
              <span className="badge">⏱ {formatDuration(session.durationSeconds)}</span>
            )}
            {delta !== null && (
              <span className={`badge ${delta > 0 ? 'up' : delta < 0 ? 'down' : ''}`}>
                {delta > 0 ? `▲ +${delta}` : delta < 0 ? `▼ ${delta}` : '='} pts depuis ta dernière tentative
              </span>
            )}
            <span className="badge">Joué par {session.player}</span>
          </div>
          {rank > 0 && ranking.length > 1 && (
            <div className="social-callout">
              <Icon name="award" size={18} />
              {rank === 1
                ? `Meilleur score de la classe sur ${ranking.length} joueurs !`
                : `${place(rank)} place sur ${ranking.length} joueurs de la classe.`}
            </div>
          )}
        </div>
      </div>

      <h2 style={{ marginBottom: '0.3rem' }}>À retenir</h2>
      <p className="muted" style={{ marginBottom: '0.9rem' }}>
        {mistakes.length
          ? `${plural(mistakes.length, 'notion')} à revoir : compare ta réponse à la bonne, puis lis l’explication.`
          : 'Aucune erreur sur ce quiz.'}
      </p>

      {mistakes.length > 0 ? (
        <div className="lessons">
          {mistakes.map(({ answer, position }) => (
            <Lesson key={answer.questionId} answer={answer} position={position} />
          ))}
        </div>
      ) : (
        <div className="notice" style={{ marginBottom: '1.75rem' }}>
          Rien à revoir — tu peux défier tes camarades sur ce quiz. <Icon name="sparkles" size={16} />
        </div>
      )}

      {successes.length > 0 && (
        <details className="mastered-list" open={mistakes.length === 0}>
          <summary><Icon name="check" size={16} strokeWidth={2.5} /> {plural(successes.length, 'bonne réponse')} — revoir les explications</summary>
          <div className="lessons">
            {successes.map(({ answer, position }) => (
              <Lesson key={answer.questionId} answer={answer} position={position} />
            ))}
          </div>
        </details>
      )}

      {session.quizId && (
        <>
          <h2 style={{ marginBottom: '0.75rem' }}>Classement de la classe</h2>
          <div className="card">
            <Ranking sessions={ranking} highlightSessionId={session.id} />
          </div>
        </>
      )}
    </div>
  )
}
