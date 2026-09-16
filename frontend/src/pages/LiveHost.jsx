import { useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import { assetUrl } from '../api'
import Confetti from '../components/Confetti'
import { LeaderboardTable, Podium, Tile, Timer, formatPin, useLiveGame, useRemainingMs } from '../components/live'
import MusicToggle from '../components/MusicToggle'
import { ErrorBox, Loader } from '../components/ui'
import { Icon } from '../components/Icon'

/** Écran de l'animateur, pensé pour être projeté devant la classe. */
export default function LiveHost() {
  const { pin } = useParams()
  const { game, error, act } = useLiveGame(pin)
  const remainingMs = useRemainingMs(game)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState(null)

  /** Envoie une action de l'animateur (étape suivante, arrêt) et affiche l'erreur éventuelle. */
  async function run(method, path) {
    setBusy(true)
    setActionError(null)
    try {
      await act(method, path)
    } catch (err) {
      setActionError(err)
    } finally {
      setBusy(false)
    }
  }

  /** Arrête la partie après confirmation. */
  function stop() {
    if (confirm('Arrêter la partie ? Les questions déjà posées restent comptées dans l’historique des joueurs.')) {
      run('DELETE')
    }
  }

  if (!game) {
    return (
      <div className="page live-shell">
        {error ? (
          <>
            <ErrorBox error={error} />
            <Link className="btn" to="/" style={{ marginTop: '1rem' }}>
              Retour à la bibliothèque
            </Link>
          </>
        ) : (
          <Loader count={1} />
        )}
      </div>
    )
  }

  if (!game.isHost) return <Navigate to={`/live/${pin}`} replace />

  const { status, question } = game
  const revealed = status === 'reveal'
  const isLast = game.questionIndex + 1 >= game.questionCount

  return (
    <div className="page live-shell">
      <header className="live-bar" style={{ marginBottom: '1.25rem' }}>
        <div>
          <span className="badge accent">En direct · PIN {formatPin(game.pin)}</span>
          <h1 style={{ marginTop: '0.4rem' }}>{game.quizTitle}</h1>
        </div>
        <span className="row" style={{ gap: '0.35rem' }}>
          <MusicToggle playing={status === 'lobby' || status === 'question'} />
          {status !== 'finished' && (
            <button type="button" className="btn danger sm" onClick={stop} disabled={busy}>
              Arrêter la partie
            </button>
          )}
        </span>
      </header>

      <ErrorBox error={actionError} />

      {status === 'lobby' && (
        <div className="stack">
          <div className="card pin-card">
            {game.coverImage && <img className="live-image" src={assetUrl(game.coverImage)} alt="" />}
            <p style={{ color: 'var(--text-muted)' }}>
              Rendez-vous sur <b style={{ color: 'var(--text)' }}>{window.location.host}/join</b> avec le code
            </p>
            <div className="pin">{formatPin(game.pin)}</div>
          </div>

          <div className="live-bar">
            <h2>
              Joueurs <span className="badge accent">{game.players.length}</span>
            </h2>
            <button
              type="button"
              className="btn primary"
              onClick={() => run('POST', '/next')}
              disabled={busy || game.players.length === 0}
            >
              Lancer la partie
            </button>
          </div>

          {game.players.length ? (
            <div className="player-chips">
              {game.players.map((name) => (
                <span className="player-chip" key={name}>
                  {name}
                </span>
              ))}
            </div>
          ) : (
            <div className="empty">
              <h3>En attente des joueurs…</h3>
              <p>Leur pseudo s’affiche ici dès qu’ils rejoignent la partie.</p>
            </div>
          )}
        </div>
      )}

      {(status === 'question' || revealed) && question && (
        <div className="stack">
          <div className="live-bar">
            <span className="badge">
              Question {game.questionIndex + 1} / {game.questionCount}
            </span>
            {status === 'question' && <Timer remainingMs={remainingMs} total={question.timeLimit} />}
            <span className="badge">
              {game.answeredCount} / {game.players.length} réponse{game.answeredCount > 1 ? 's' : ''}
            </span>
          </div>

          <p className="live-question">{question.text}</p>
          {question.image && <img className="live-image" src={assetUrl(question.image)} alt="" />}

          <div className="tiles">
            {question.choices.map((choice, index) => {
              const correct = revealed && index === question.correctIndex
              const count = game.distribution?.[index] ?? 0

              return (
                <Tile key={index} index={index} className={revealed ? (correct ? 'correct' : 'dim') : ''}>
                  <span className="label">{choice}</span>
                  {revealed && (
                    <>
                      <span className="count">
                        {correct && <Icon name="check" size={18} strokeWidth={3} style={{ marginRight: '0.25rem' }} />}
                        {count}
                      </span>
                      <span
                        className="bar"
                        style={{ width: `${game.answeredCount ? (count / game.answeredCount) * 100 : 0}%` }}
                      />
                    </>
                  )}
                </Tile>
              )
            })}
          </div>

          {revealed && question.explanation && <p className="live-explain">{question.explanation}</p>}

          {revealed && (
            <div className="card">
              <h2 style={{ marginBottom: '0.75rem' }}>Classement</h2>
              <LeaderboardTable rows={game.leaderboard} showLastPoints />
            </div>
          )}

          <div className="row" style={{ justifyContent: 'flex-end' }}>
            {revealed ? (
              <button type="button" className="btn primary" onClick={() => run('POST', '/next')} disabled={busy}>
                {isLast ? 'Voir le podium' : 'Question suivante'}
              </button>
            ) : (
              <button type="button" className="btn" onClick={() => run('POST', '/next')} disabled={busy}>
                Afficher la réponse
              </button>
            )}
          </div>
        </div>
      )}

      {status === 'finished' && (
        <div className="stack">
          {game.leaderboard?.length ? (
            <>
              <Confetti />
              <Podium leaderboard={game.leaderboard} />
              <div className="card">
                <LeaderboardTable rows={game.leaderboard} />
              </div>
            </>
          ) : (
            <div className="empty">
              <h3>Partie arrêtée</h3>
              <p>Aucun joueur n’a participé.</p>
            </div>
          )}
          <div className="row" style={{ justifyContent: 'center' }}>
            <Link className="btn primary" to="/">
              Bibliothèque
            </Link>
            <Link className="btn ghost" to={`/quiz/${game.quizId}/scores`}>
              Classement du quiz
            </Link>
          </div>
        </div>
      )}
    </div>
  )
}
