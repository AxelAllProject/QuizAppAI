import { Link } from 'react-router-dom'
import { assetUrl } from '../api'

export function Field({ label, hint, children }) {
  return (
    <div className="field">
      <label>{label}</label>
      {children}
      {hint && <span className="hint">{hint}</span>}
    </div>
  )
}

/** Affiche soit un message unique, soit la liste des violations de validation. */
export function ErrorBox({ error }) {
  if (!error) return null

  return (
    <div className="alert" role="alert">
      {error.message}
      {error.messages?.length > 0 && (
        <ul>
          {error.messages.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      )}
    </div>
  )
}

export function Empty({ title, children, action }) {
  return (
    <div className="empty">
      <h3>{title}</h3>
      <p>{children}</p>
      {action && <div style={{ marginTop: '1rem' }}>{action}</div>}
    </div>
  )
}

export function Loader({ count = 3 }) {
  return (
    <div className="grid">
      {Array.from({ length: count }, (_, i) => (
        <div className="skeleton" key={i} />
      ))}
    </div>
  )
}

export function QuizCard({ quiz, onDelete, onHost }) {
  return (
    <article className="card quiz-card" data-difficulty={quiz.difficulty}>
      {quiz.coverImage ? (
        <img className="quiz-cover" src={assetUrl(quiz.coverImage)} alt="" loading="lazy" />
      ) : (
        <div className="quiz-cover-placeholder" aria-hidden="true">
          <span>{quiz.title.trim().slice(0, 1).toUpperCase() || '?'}</span>
        </div>
      )}
      <div className="row">
        <span className={`badge ${quiz.difficulty}`}>{quiz.difficulty}</span>
        <span className="badge">{quiz.category}</span>
      </div>

      <h3>{quiz.title}</h3>
      <p className="desc">{quiz.description || 'Pas de description.'}</p>

      <div className="meta">
        {quiz.questionCount} question{quiz.questionCount > 1 ? 's' : ''} · par {quiz.author}
      </div>

      <footer>
        <span className="row" style={{ gap: '0.35rem' }}>
          <Link className="btn primary sm" to={`/quiz/${quiz.id}`}>
            Jouer
          </Link>
          {onHost && (
            <button type="button" className="btn sm" onClick={() => onHost(quiz)} title="Animer une partie façon Kahoot">
              En direct
            </button>
          )}
          <Link className="btn ghost sm" to={`/quiz/${quiz.id}/scores`}>
            Classement
          </Link>
        </span>
        {quiz.canEdit && (
          <span className="row" style={{ gap: '0.35rem' }}>
            <Link className="btn ghost sm" to={`/quiz/${quiz.id}/edit`}>
              Modifier
            </Link>
            <button type="button" className="btn danger sm" onClick={() => onDelete(quiz)}>
              Supprimer
            </button>
          </span>
        )}
      </footer>
    </article>
  )
}

export function ScoreRing({ accuracy }) {
  return (
    <div className="score-ring" style={{ '--value': accuracy }}>
      <div>
        {accuracy}%<small>de réussite</small>
      </div>
    </div>
  )
}

export function formatDate(iso) {
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) return '—'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return m > 0 ? `${m} min ${String(s).padStart(2, '0')}` : `${s} s`
}
