import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import communityArt from '../assets/community.svg'
import { ClassPodium, CommunityPulse, Leaderboard } from '../components/community'
import { Icon } from '../components/Icon'
import { Avatar, ErrorBox, formatDate, subjectOf } from '../components/ui'

/** La classe dans son ensemble : qui progresse, qui partage, quoi relever ensemble. */
export default function Community() {
  const [stats, setStats] = useState(null)
  const [quizzes, setQuizzes] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    Promise.all([api('/api/stats'), api('/api/quizzes')])
      .then(([nextStats, nextQuizzes]) => {
        setStats(nextStats)
        setQuizzes(nextQuizzes)
      })
      .catch(setError)
  }, [])

  const latest = useMemo(
    () => [...(quizzes ?? [])].sort((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 5),
    [quizzes],
  )

  const authors = useMemo(() => {
    const counts = new Map()
    for (const quiz of quizzes ?? []) {
      if (quiz.author) counts.set(quiz.author, (counts.get(quiz.author) ?? 0) + 1)
    }
    return [...counts].sort((a, b) => b[1] - a[1]).slice(0, 5)
  }, [quizzes])

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <span className="eyebrow">
            <Icon name="users" size={14} /> Communauté
          </span>
          <h1 style={{ marginTop: '0.4rem' }}>La classe QuizLab</h1>
          <p>Qui progresse, qui partage ses quiz, et les derniers défis à relever ensemble.</p>
        </div>
        <img className="head-art" src={communityArt} alt="" />
      </header>

      <ErrorBox error={error} />

      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <CommunityPulse stats={stats} />
      </div>

      <div className="two-col">
        <section className="card">
          <h2>
            <Icon name="trophy" size={20} /> Classement général
          </h2>
          <p>Classé au taux de bonnes réponses, puis au nombre de parties jouées.</p>
          {!stats ? (
            <div className="skeleton" />
          ) : stats.leaderboard.length ? (
            <>
              <ClassPodium rows={stats.leaderboard} />
              <Leaderboard rows={stats.leaderboard} offset={Math.min(3, stats.leaderboard.length)} />
            </>
          ) : (
            <p className="muted">Personne n’a encore joué : ouvre la voie !</p>
          )}
        </section>

        <div className="stack">
          <section className="card">
            <h2>
              <Icon name="sparkles" size={20} /> Nouveaux défis
            </h2>
            <p>Les derniers quiz publiés : sois parmi les premiers à les relever.</p>
            {!quizzes ? (
              <div className="skeleton" />
            ) : latest.length ? (
              <ul className="item-list challenges">
                {latest.map((quiz, index) => (
                  <li key={quiz.id} style={{ '--hue': subjectOf(quiz.category).hue }}>
                    <span className="ico">
                      <Icon name={subjectOf(quiz.category).icon} size={22} />
                    </span>
                    <div>
                      <b>
                        {quiz.title}
                        {index === 0 && <span className="badge new">Nouveau</span>}
                      </b>
                      <small>
                        {quiz.category} · par {quiz.author || 'un ancien membre'} · {formatDate(quiz.createdAt)}
                      </small>
                    </div>
                    <Link className="btn primary sm" to={`/quiz/${quiz.id}`}>
                      Relever
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="muted">Aucun quiz publié pour le moment.</p>
            )}
          </section>

          {authors.length > 0 && (
            <section className="card">
              <h2>
                <Icon name="pen" size={20} /> Ils partagent leurs quiz
              </h2>
              <p>Merci à celles et ceux qui font vivre la bibliothèque.</p>
              <ul className="item-list authors">
                {authors.map(([author, count]) => (
                  <li key={author}>
                    <Avatar name={author} size="sm" />
                    <div>
                      <b>{author}</b>
                      <span className="meter thin" aria-hidden="true">
                        <span style={{ width: `${(count / authors[0][1]) * 100}%` }} />
                      </span>
                    </div>
                    <span className="badge accent">{count} quiz</span>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      </div>
    </div>
  )
}
