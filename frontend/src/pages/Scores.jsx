import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api'
import Ranking from '../components/Ranking'
import { ErrorBox } from '../components/ui'

export default function Scores() {
  const { id } = useParams()
  const [quiz, setQuiz] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api(`/api/quizzes/${id}`).then(setQuiz).catch(setError)
  }, [id])

  return (
    <div className="page play-shell">
      <header className="page-head">
        <div>
          <h1>Classement</h1>
          <p>{quiz?.title ?? 'Chargement…'}</p>
        </div>
        <div className="row">
          <Link className="btn primary" to={`/quiz/${id}`}>
            Jouer
          </Link>
          <Link className="btn ghost" to="/">
            Bibliothèque
          </Link>
        </div>
      </header>

      <ErrorBox error={error} />

      <div className="card">
        <Ranking quizId={id} />
      </div>
    </div>
  )
}
