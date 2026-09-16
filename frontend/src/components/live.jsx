import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../api'

/** Une couleur et une forme par réponse, comme sur un plateau Kahoot : repérables sans lire. */
export const TILES = [
  { shape: '▲', color: '#e21b3c' },
  { shape: '◆', color: '#1368ce' },
  { shape: '●', color: '#c68a00' },
  { shape: '■', color: '#26890c' },
  { shape: '★', color: '#864cbf' },
  { shape: '⬢', color: '#0a8585' },
]

/** Intervalle entre deux lectures de l'état d'une partie en direct. */
const POLL_MS = 1000

/**
 * État d'une partie en direct, relu chaque seconde. Les réponses arrivées dans le
 * désordre sont ignorées : une vieille lecture n'écrase pas le résultat d'une action.
 */
export function useLiveGame(pin) {
  const [game, setGame] = useState(null)
  const [error, setError] = useState(null)
  const sent = useRef(0)
  const applied = useRef(0)

  const track = useCallback(async (request) => {
    const id = ++sent.current
    const next = await request
    if (id > applied.current) {
      applied.current = id
      setGame({ ...next, receivedAt: performance.now() })
      setError(null)
    }
    return next
  }, [])

  const finished = game?.status === 'finished'

  useEffect(() => {
    if (finished) return undefined

    /** Relit l'état de la partie. */
    const poll = () => track(api(`/api/live-games/${pin}`)).catch(setError)
    poll()
    const timer = setInterval(poll, POLL_MS)
    return () => clearInterval(timer)
  }, [pin, track, finished])

  const act = useCallback(
    (method, path = '', body) => track(api(`/api/live-games/${pin}${path}`, { method, body })),
    [pin, track],
  )

  return { game, error, act }
}

/** Temps restant, décompté localement entre deux lectures de l'état. */
export function useRemainingMs(game) {
  const [now, setNow] = useState(() => performance.now())
  const running = game?.status === 'question'

  useEffect(() => {
    if (!running) return undefined
    const timer = setInterval(() => setNow(performance.now()), 200)
    return () => clearInterval(timer)
  }, [running])

  if (!running) return 0
  return Math.max(0, game.remainingMs - Math.max(0, now - game.receivedAt))
}

/** Chrono circulaire de la question en cours, en rouge sur les 5 dernières secondes. */
export function Timer({ remainingMs, total }) {
  const seconds = Math.ceil(remainingMs / 1000)

  return (
    <div
      className={`timer ${seconds <= 5 ? 'urgent' : ''}`}
      style={{ '--p': total ? (remainingMs / (total * 1000)) * 100 : 0 }}
      role="timer"
      aria-label={`${seconds} secondes restantes`}
    >
      <span>{seconds}</span>
    </div>
  )
}

/** Tuile de réponse avec sa forme et sa couleur, cliquable ou non. */
export function Tile({ index, className = '', onClick, disabled, children }) {
  const { shape, color } = TILES[index % TILES.length]
  const content = (
    <>
      <span className="shape" aria-hidden="true">
        {shape}
      </span>
      {children}
    </>
  )

  return onClick ? (
    <button type="button" className={`tile ${className}`} style={{ '--tile': color }} onClick={onClick} disabled={disabled}>
      {content}
    </button>
  ) : (
    <div className={`tile ${className}`} style={{ '--tile': color }}>
      {content}
    </div>
  )
}

/** Affiche un code PIN en deux groupes de trois chiffres. */
export function formatPin(pin) {
  return `${pin.slice(0, 3)} ${pin.slice(3)}`
}

/** Écrit un rang en toutes lettres courtes (1er, 2e…). */
export function ordinal(rank) {
  return rank === 1 ? '1er' : `${rank}e`
}

/** Podium des trois premiers joueurs. */
export function Podium({ leaderboard }) {
  const [first, second, third] = leaderboard
  const steps = [
    [second, 2],
    [first, 1],
    [third, 3],
  ].filter(([row]) => row)

  return (
    <div className="podium">
      {steps.map(([row, rank]) => (
        <div className={`step p${rank}`} key={rank}>
          <div className="name">{row.nickname}</div>
          <div className="score">{row.score} pts</div>
          <div className="block">{rank}</div>
        </div>
      ))}
    </div>
  )
}

/** Tableau du classement, avec les points de la dernière question si demandé. */
export function LeaderboardTable({ rows, showLastPoints = false }) {
  return (
    <div style={{ overflowX: 'auto' }}>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Joueur</th>
            {showLastPoints && <th>Cette question</th>}
            <th>Bonnes réponses</th>
            <th>Score</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.nickname}>
              <td style={{ color: 'var(--text-faint)' }}>{row.rank}</td>
              <td>{row.nickname}</td>
              {showLastPoints && (
                <td style={{ color: row.lastPoints ? 'var(--success)' : 'var(--text-faint)' }}>
                  {row.lastPoints ? `+${row.lastPoints}` : '—'}
                </td>
              )}
              <td>{row.correctCount}</td>
              <td>
                <b>{row.score}</b>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
