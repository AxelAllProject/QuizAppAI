import { useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import { assetUrl } from '../api'
import Confetti from '../components/Confetti'
import { Podium, Tile, Timer, formatPin, ordinal, useLiveGame, useRemainingMs } from '../components/live'
import { ErrorBox, Loader } from '../components/ui'

const MEDALS = { 1: '🥇', 2: '🥈', 3: '🥉' }

/** Écran d'un joueur : sur téléphone, les réponses sont de grosses tuiles colorées. */
export default function LivePlayer() {
  const { pin } = useParams()
  const { game, error, act } = useLiveGame(pin)
  const remainingMs = useRemainingMs(game)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState(null)

  async function run(method, path, body) {
    setBusy(true)
    setActionError(null)
    try {
      await act(method, path, body)
    } catch (err) {
      setActionError(err)
    } finally {
      setBusy(false)
    }
  }

  if (!game) {
    return (
      <div className="page play-shell">
        {error?.status === 403 ? (
          <div className="card stack" style={{ maxWidth: 440, margin: '2rem auto', textAlign: 'center' }}>
            <h1>Partie {formatPin(pin)}</h1>
            <p style={{ color: 'var(--text-muted)' }}>Tu n’as pas encore rejoint cette partie.</p>
            <ErrorBox error={actionError} />
            <button type="button" className="btn primary block" onClick={() => run('POST', '/join')} disabled={busy}>
              Rejoindre
            </button>
          </div>
        ) : error ? (
          <>
            <ErrorBox error={error} />
            <Link className="btn" to="/join" style={{ marginTop: '1rem' }}>
              Saisir un autre code
            </Link>
          </>
        ) : (
          <Loader count={1} />
        )}
      </div>
    )
  }

  if (game.isHost) return <Navigate to={`/live/${pin}/host`} replace />

  const { status, question, me } = game
  const answer = me?.answer
  const myRow = game.leaderboard?.find((row) => row.nickname === me?.nickname)

  return (
    <div className="page play-shell">
      <header className="live-bar" style={{ marginBottom: '1rem' }}>
        <div>
          <span className="badge accent">PIN {formatPin(game.pin)}</span>
          <h1 style={{ marginTop: '0.4rem', fontSize: '1.4rem' }}>{game.quizTitle}</h1>
        </div>
        {me && (
          <div style={{ textAlign: 'right', lineHeight: 1.3 }}>
            <div style={{ fontWeight: 700 }}>{me.nickname}</div>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>{me.score} pts</div>
          </div>
        )}
      </header>

      <ErrorBox error={actionError} />

      {status === 'lobby' && (
        <div className="feedback wait">
          <h2>Tu es dans la partie !</h2>
          <p>Ton pseudo s’affiche sur l’écran de l’animateur. La partie va bientôt commencer…</p>
          <p style={{ marginTop: '1rem', color: 'var(--text-muted)' }}>
            {game.players.length} joueur{game.players.length > 1 ? 's' : ''} connecté{game.players.length > 1 ? 's' : ''}
          </p>
        </div>
      )}

      {status === 'question' && question && (
        answer ? (
          <div className="feedback wait">
            <h2>Réponse envoyée</h2>
            <p>Verdict à la fin du chrono…</p>
            <div style={{ maxWidth: 380, margin: '1.25rem auto 0' }}>
              <Tile index={answer.choiceIndex}>
                <span className="label">{question.choices[answer.choiceIndex]}</span>
              </Tile>
            </div>
          </div>
        ) : (
          <div className="stack">
            <div className="live-bar">
              <span className="badge">
                Question {game.questionIndex + 1} / {game.questionCount}
              </span>
              <Timer remainingMs={remainingMs} total={question.timeLimit} />
            </div>
            <p className="live-question" style={{ fontSize: '1.35rem' }}>
              {question.text}
            </p>
            {question.image && <img className="live-image" src={assetUrl(question.image)} alt="" />}
            <div className="tiles">
              {question.choices.map((choice, index) => (
                <Tile key={index} index={index} onClick={() => run('POST', '/answers', { choiceIndex: index })} disabled={busy || remainingMs === 0}>
                  <span className="label">{choice}</span>
                </Tile>
              ))}
            </div>
          </div>
        )
      )}

      {status === 'reveal' && question && me && (
        <div className={`feedback ${answer?.correct ? 'good' : 'bad'}`}>
          {answer?.correct && <Confetti count={40} />}
          <h2>{!answer ? 'Temps écoulé' : answer.correct ? 'Bonne réponse !' : 'Raté…'}</h2>
          {answer?.correct ? (
            <div className="points">+{answer.points} points</div>
          ) : (
            <p style={{ marginTop: '0.5rem' }}>
              La bonne réponse était : <b>{question.choices[question.correctIndex]}</b>
            </p>
          )}
          <p style={{ marginTop: '1.25rem' }}>
            Tu es <b>{ordinal(me.rank)}</b> avec {me.score} points
          </p>
        </div>
      )}

      {status === 'finished' && (
        <div className="stack">
          {me?.rank <= 3 && <Confetti />}
          <div className="feedback wait">
            {me ? (
              <>
                <div style={{ fontSize: '3rem', lineHeight: 1 }}>{MEDALS[me.rank] ?? '🎉'}</div>
                <h2 style={{ marginTop: '0.5rem' }}>
                  {ordinal(me.rank)} sur {game.players.length}
                </h2>
                <p>
                  {me.score} points · {myRow?.correctCount ?? 0} bonne{myRow?.correctCount > 1 ? 's' : ''} réponse
                  {myRow?.correctCount > 1 ? 's' : ''}
                </p>
              </>
            ) : (
              <h2>Partie terminée</h2>
            )}
          </div>
          {game.leaderboard?.length > 0 && <Podium leaderboard={game.leaderboard} />}
          <div className="row" style={{ justifyContent: 'center' }}>
            <Link className="btn primary" to="/history">
              Voir ma correction
            </Link>
            <Link className="btn ghost" to="/">
              Bibliothèque
            </Link>
          </div>
        </div>
      )}
    </div>
  )
}
