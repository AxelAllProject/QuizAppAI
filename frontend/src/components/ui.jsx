import { Link } from 'react-router-dom'
import { assetUrl } from '../api'
import emptyArt from '../assets/empty-notebook.svg'
import { Icon } from './Icon'
import { plural, progressOf } from '../progress'

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
      <img className="empty-art" src={emptyArt} alt="" />
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

/** Une icône par grande matière, reconnue dans le libellé libre de la catégorie. */
const SUBJECT_ICONS = [
  [/math|calcul|géom|algèbr|nombre/i, 'calculator'],
  [/hist/i, 'landmark'],
  [/géo|pays|capitale|monde/i, 'map'],
  [/scien|physi|chimi|bio|svt|nature|espace|astro|anima/i, 'flask'],
  [/fran|littér|gramm|orthog|conjug|lecture/i, 'book-open'],
  [/angl|espagn|allem|ital|langue|english/i, 'globe'],
  [/art|musi|ciné|peint/i, 'palette'],
  [/sport/i, 'ball'],
  [/info|code|program|numér|techno|web/i, 'laptop'],
  [/cultur|général/i, 'lightbulb'],
]

/** Teinte stable dérivée d'un texte : même matière ou même pseudo, même couleur partout. */
export function hueOf(text = '') {
  let hue = 0
  for (const char of text.toLowerCase()) hue = (hue * 31 + char.codePointAt(0)) % 360
  return hue
}

export function subjectOf(category = '') {
  return {
    icon: SUBJECT_ICONS.find(([pattern]) => pattern.test(category))?.[1] ?? 'book',
    hue: hueOf(category),
  }
}

export function Subject({ category }) {
  const { icon, hue } = subjectOf(category)
  return (
    <span className="subject" style={{ '--hue': hue }}>
      <Icon name={icon} size={14} /> {category || 'Divers'}
    </span>
  )
}

export function Avatar({ name, size = '' }) {
  const label = name || '?'
  return (
    <span className={`avatar ${size}`} style={{ '--hue': hueOf(label) }} aria-hidden="true">
      {label.slice(0, 2).toUpperCase()}
    </span>
  )
}

export function QuizCard({ quiz, best, onDelete, onHost }) {
  const { icon, hue } = subjectOf(quiz.category)
  const progress = progressOf(best)

  return (
    <article className="card quiz-card" data-difficulty={quiz.difficulty} style={{ '--hue': hue }}>
      {quiz.coverImage ? (
        <img className="quiz-cover" src={assetUrl(quiz.coverImage)} alt="" loading="lazy" />
      ) : (
        <div className="quiz-cover-placeholder" aria-hidden="true">
          <Icon name={icon} size={56} strokeWidth={1.5} />
        </div>
      )}
      <div className="row" style={{ gap: '0.4rem' }}>
        <Subject category={quiz.category} />
        <span className={`badge ${quiz.difficulty}`}>{quiz.difficulty}</span>
        {progress && (
          <span className={`quiz-progress ${progress.tone}`}>
            {progress.label} · {best}%
          </span>
        )}
      </div>

      <h3>{quiz.title}</h3>
      <p className="desc">{quiz.description || 'Pas de description.'}</p>

      <div className="byline">
        <Avatar name={quiz.author} size="xs" />
        par {quiz.author || 'un ancien membre'} · {plural(quiz.questionCount, 'question')}
      </div>

      <footer>
        <span className="row" style={{ gap: '0.35rem' }}>
          <Link className="btn primary sm" to={`/quiz/${quiz.id}`}>
            {best === undefined ? 'Jouer' : 'Rejouer'}
          </Link>
          {onHost && (
            <button type="button" className="btn sm" onClick={() => onHost(quiz)} title="Code PIN, chrono et podium pour toute la classe">
              Lancer en classe
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
