import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../api'
import heroArt from '../assets/hero-study.svg'
import { useAuth } from '../auth'
import { CommunityAside } from '../components/community'
import JoinForm from '../components/JoinForm'
import { Empty, ErrorBox, Loader, QuizCard, subjectOf } from '../components/ui'
import { summarize } from '../progress'
import { Icon } from '../components/Icon'

export default function Library() {
  const { user, canCreate } = useAuth()
  const navigate = useNavigate()
  const [quizzes, setQuizzes] = useState([])
  const [categories, setCategories] = useState([])
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [onlyMine, setOnlyMine] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [history, setHistory] = useState([])
  const [stats, setStats] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    const params = new URLSearchParams()
    if (search.trim()) params.set('search', search.trim())
    if (category) params.set('category', category)
    if (onlyMine) params.set('mine', '1')

    try {
      const [list, cats] = await Promise.all([
        api(`/api/quizzes?${params}`),
        api('/api/categories'),
      ])
      setQuizzes(list)
      setCategories(cats)
    } catch (err) {
      setError(err)
    } finally {
      setLoading(false)
    }
  }, [search, category, onlyMine])

  // Petit debounce pour ne pas requêter à chaque frappe.
  useEffect(() => {
    const timer = setTimeout(load, 250)
    return () => clearTimeout(timer)
  }, [load])

  // Parcours et communauté enrichissent l'accueil, sans le bloquer s'ils échouent.
  useEffect(() => {
    api('/api/sessions').then(setHistory).catch(() => {})
    api('/api/stats').then(setStats).catch(() => {})
  }, [])

  const learning = useMemo(() => summarize(history), [history])

  async function host(quiz) {
    setError(null)
    try {
      const game = await api('/api/live-games', { method: 'POST', body: { quizId: quiz.id } })
      navigate(`/live/${game.pin}/host`)
    } catch (err) {
      setError(err)
    }
  }

  async function remove(quiz) {
    if (!confirm(`Supprimer « ${quiz.title} » ? Cette action est définitive.`)) return

    try {
      await api(`/api/quizzes/${quiz.id}`, { method: 'DELETE' })
      setQuizzes((current) => current.filter((item) => item.id !== quiz.id))
    } catch (err) {
      setError(err)
    }
  }

  return (
    <div className="page" style={{ maxWidth: 1240 }}>
      <section className="hero">
        <div className="card hero-main">
          <span className="eyebrow"><Icon name="book-open" size={14} /> Apprendre ensemble</span>
          <h1>Salut {user.name} !</h1>
          <p>
            {canCreate
              ? 'Prépare un quiz pour ta classe, lance une partie en direct et suis les progrès de chacun.'
              : history.length
                ? 'Reprends là où tu t’es arrêté : chaque partie te laisse une correction expliquée pour progresser.'
                : 'Choisis un premier quiz : chaque réponse est corrigée et expliquée, à ton rythme.'}
          </p>

          <div className="hero-stats">
            <div className="hero-stat">
              <b>{history.length}</b>
              <span>{history.length > 1 ? 'parties jouées' : 'partie jouée'}</span>
            </div>
            <div className="hero-stat">
              <b>{learning.average === null ? '—' : `${learning.average}%`}</b>
              <span>de réussite moyenne</span>
            </div>
            <div className="hero-stat">
              <b>{learning.mastered}</b>
              <span>{learning.mastered > 1 ? 'quiz maîtrisés' : 'quiz maîtrisé'}</span>
            </div>
            {learning.toReview.length > 0 && (
              <Link className="hero-stat" to="/history">
                <b>{learning.toReview.length}</b>
                <span>à revoir →</span>
              </Link>
            )}
          </div>

          {canCreate && (
            <div className="row" style={{ marginTop: '1.35rem' }}>
              <Link className="btn primary" to="/create">
                + Créer un quiz
              </Link>
            </div>
          )}

          <img className="hero-art" src={heroArt} alt="" />
        </div>

        <JoinForm className="card join-card">
          <h2>
            <Icon name="gamepad" size={20} /> Partie en classe
          </h2>
          <p>Ton professeur affiche un code à l’écran ? Entre-le pour jouer avec toute la classe.</p>
        </JoinForm>
      </section>

      <div className="home-layout">
        <section>
          <div className="section-title">
            <div>
              <h2>Bibliothèque</h2>
              <p>
                {loading ? 'Des' : quizzes.length} quiz rédigés par les professeurs, à explorer par matière.
              </p>
            </div>
          </div>

          <div className="filters">
            <div className="search-row">
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher un quiz, un thème…" />
              {canCreate && (
                <button
                  type="button"
                  className={`btn ${onlyMine ? 'primary' : ''}`}
                  aria-pressed={onlyMine}
                  onClick={() => setOnlyMine((value) => !value)}
                >
                  Mes quiz
                </button>
              )}
            </div>
            {categories.length > 0 && (
              <div className="chips" role="group" aria-label="Matières">
                <button type="button" className="chip" aria-pressed={!category} onClick={() => setCategory('')}>
                  Toutes les matières
                </button>
                {categories.map((item) => (
                  <button
                    key={item}
                    type="button"
                    className="chip"
                    aria-pressed={category === item}
                    onClick={() => setCategory(category === item ? '' : item)}
                  >
                    <Icon name={subjectOf(item).icon} size={14} /> {item}
                  </button>
                ))}
              </div>
            )}
          </div>

          <ErrorBox error={error} />

          {loading ? (
            <Loader />
          ) : quizzes.length === 0 ? (
            <Empty
              title="Aucun quiz ici"
              action={
                canCreate ? (
                  <Link className="btn primary" to="/create">
                    Créer le premier
                  </Link>
                ) : null
              }
            >
              {canCreate
                ? 'Change les filtres, ou publie le premier questionnaire.'
                : 'Change les filtres — les quiz sont rédigés par les professeurs.'}
            </Empty>
          ) : (
            <div className="grid">
              {quizzes.map((quiz) => (
                <QuizCard
                  key={quiz.id}
                  quiz={quiz}
                  best={learning.best.get(quiz.id)}
                  onDelete={remove}
                  onHost={canCreate ? host : undefined}
                />
              ))}
            </div>
          )}
        </section>

        <CommunityAside stats={stats} />
      </div>
    </div>
  )
}
