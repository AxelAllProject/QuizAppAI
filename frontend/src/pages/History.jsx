import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { Empty, ErrorBox, Loader, formatDate, formatDuration, subjectOf } from '../components/ui'
import { bestByQuiz, summarize, toneOf } from '../progress'
import { Icon } from '../components/Icon'

/** Maîtrise par matière : moyenne des meilleurs scores obtenus sur chaque quiz de la matière. */
function masteryBySubject(sessions, quizzes) {
  const categoryOf = new Map(quizzes.map((quiz) => [quiz.id, quiz.category]))
  const subjects = new Map()

  for (const [quizId, accuracy] of bestByQuiz(sessions)) {
    const category = categoryOf.get(quizId) ?? 'Autres quiz'
    subjects.set(category, [...(subjects.get(category) ?? []), accuracy])
  }

  return [...subjects]
    .map(([category, scores]) => ({
      category,
      quizCount: scores.length,
      accuracy: Math.round(scores.reduce((sum, value) => sum + value, 0) / scores.length),
    }))
    .sort((a, b) => b.accuracy - a.accuracy)
}

/** Page « Mon parcours » : historique des parties et maîtrise par matière. */
export default function History() {
  const [sessions, setSessions] = useState(null)
  const [quizzes, setQuizzes] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    api('/api/sessions').then(setSessions).catch(setError)
    // Les catégories ne servent qu'au regroupement par matière : facultatives.
    api('/api/quizzes').then(setQuizzes).catch(() => {})
  }, [])

  const learning = useMemo(() => summarize(sessions ?? []), [sessions])
  const subjects = useMemo(() => masteryBySubject(sessions ?? [], quizzes), [sessions, quizzes])

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <span className="eyebrow"><Icon name="compass" size={14} /> Mon parcours</span>
          <h1 style={{ marginTop: '0.4rem' }}>Mes progrès</h1>
          <p>Matière par matière, ce que tu maîtrises et ce qui mérite une révision.</p>
        </div>
      </header>

      <ErrorBox error={error} />

      {sessions === null ? (
        !error && <Loader count={2} />
      ) : sessions.length === 0 ? (
        <Empty
          title="Ton parcours commence ici"
          action={
            <Link className="btn primary" to="/">
              Choisir un quiz
            </Link>
          }
        >
          Joue un premier quiz : tes progrès et tes corrections s’afficheront ici.
        </Empty>
      ) : (
        <>
          <div className="stat-grid" style={{ marginBottom: '1.5rem' }}>
            {[
              [sessions.length, 'parties jouées'],
              [`${learning.average}%`, 'de réussite moyenne'],
              [learning.best.size, 'quiz différents'],
              [learning.mastered, 'quiz maîtrisés (≥ 80 %)'],
            ].map(([value, label]) => (
              <div className="card stat" key={label}>
                <div className="value">{value}</div>
                <div className="label">{label}</div>
              </div>
            ))}
          </div>

          <div className="two-col">
            <section className="card">
              <h2>
                <Icon name="chart" size={20} /> Par matière
              </h2>
              <p>Ton meilleur score sur chaque quiz, en moyenne par matière.</p>
              <div className="mastery">
                {subjects.map((subject) => (
                  <div className="mastery-row" key={subject.category}>
                    <header>
                      <span>
                        <Icon name={subjectOf(subject.category).icon} size={16} /> {subject.category}
                      </span>
                      <span>
                        {subject.accuracy}% · {subject.quizCount} quiz
                      </span>
                    </header>
                    <div className={`meter ${toneOf(subject.accuracy)}`}>
                      <span style={{ width: `${subject.accuracy}%` }} />
                    </div>
                  </div>
                ))}
              </div>
            </section>

            <section className="card">
              <h2>
                <Icon name="refresh" size={20} /> À retravailler
              </h2>
              <p>Les quiz dont ta dernière tentative est sous 50 % : relis la correction, puis retente.</p>
              {learning.toReview.length ? (
                <ul className="item-list">
                  {learning.toReview.map((session) => (
                    <li key={session.id}>
                      <div>
                        <b>{session.quizTitle}</b>
                        <small>
                          {session.accuracy}% · {formatDate(session.playedAt)}
                        </small>
                      </div>
                      <Link className="btn ghost sm" to={`/session/${session.id}`}>
                        Correction
                      </Link>
                      <Link className="btn primary sm" to={`/quiz/${session.quizId}`}>
                        Réviser
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : (
                <div className="notice">Rien à rattraper pour l’instant — bravo ! <Icon name="sparkles" size={16} /></div>
              )}
            </section>
          </div>

          <h2 style={{ marginBottom: '0.75rem' }}>Journal des parties</h2>
          <div className="card table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Quiz</th>
                  <th>Score</th>
                  <th>Réussite</th>
                  <th>Durée</th>
                  <th>Date</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {sessions.map((session) => (
                  <tr key={session.id}>
                    <td>{session.quizTitle}</td>
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
                    <td style={{ textAlign: 'right' }}>
                      <Link className="btn ghost sm" to={`/session/${session.id}`}>
                        Correction
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
