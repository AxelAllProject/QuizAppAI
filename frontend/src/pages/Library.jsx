import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../api'
import { useAuth } from '../auth'
import { Empty, ErrorBox, Loader, QuizCard } from '../components/ui'

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
    <div className="page">
      <header className="page-head">
        <div>
          <h1>Bibliothèque de quiz</h1>
          <p>
            Salut {user.name} —{' '}
            {canCreate
              ? 'rédige un nouveau quiz ou lance une partie en direct avec ta classe.'
              : 'choisis un quiz, ou rejoins une partie en direct avec son code PIN.'}
          </p>
        </div>
        {canCreate && (
          <Link className="btn primary" to="/create">
            + Créer un quiz
          </Link>
        )}
      </header>

      <div className="card row" style={{ marginBottom: '1.5rem' }}>
        <input
          style={{ flex: '2 1 220px', width: 'auto' }}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher un quiz…"
        />
        <select
          style={{ flex: '1 1 160px', width: 'auto' }}
          value={category}
          onChange={(e) => setCategory(e.target.value)}
        >
          <option value="">Toutes les catégories</option>
          {categories.map((item) => (
            <option key={item} value={item}>
              {item}
            </option>
          ))}
        </select>
        {canCreate && (
          <button
            type="button"
            className={`btn sm ${onlyMine ? 'primary' : 'ghost'}`}
            onClick={() => setOnlyMine((value) => !value)}
          >
            Mes quiz
          </button>
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
            <QuizCard key={quiz.id} quiz={quiz} onDelete={remove} onHost={canCreate ? host : undefined} />
          ))}
        </div>
      )}
    </div>
  )
}
