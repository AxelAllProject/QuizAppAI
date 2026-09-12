import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, assetUrl } from '../api'
import { Tile } from '../components/live'
import { ErrorBox, Loader } from '../components/ui'

export default function Play() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [quiz, setQuiz] = useState(null)
  const [index, setIndex] = useState(0)
  const [answers, setAnswers] = useState({})
  const [error, setError] = useState(null)
  const [sending, setSending] = useState(false)
  const startedAt = useRef(0) // horodatage posé une fois le quiz chargé

  useEffect(() => {
    api(`/api/quizzes/${id}`)
      .then((data) => {
        setQuiz(data)
        startedAt.current = Date.now()
      })
      .catch(setError)
  }, [id])

  const question = quiz?.questions[index]
  const progress = useMemo(
    () => (quiz ? Math.round((index / quiz.questions.length) * 100) : 0),
    [quiz, index],
  )

  function choose(choiceIndex) {
    setAnswers((current) => ({ ...current, [question.id]: choiceIndex }))
  }

  async function finish() {
    setSending(true)
    setError(null)

    try {
      const session = await api(`/api/quizzes/${id}/sessions`, {
        method: 'POST',
        body: {
          answers,
          durationSeconds: Math.round((Date.now() - startedAt.current) / 1000),
        },
      })
      navigate(`/session/${session.id}`, { replace: true, state: { session } })
    } catch (err) {
      setError(err)
      setSending(false)
    }
  }

  if (error && !quiz) {
    return (
      <div className="page">
        <ErrorBox error={error} />
        <Link className="btn" to="/" style={{ marginTop: '1rem' }}>
          Retour à la bibliothèque
        </Link>
      </div>
    )
  }

  if (!quiz) {
    return (
      <div className="page play-shell">
        <Loader count={1} />
      </div>
    )
  }

  if (quiz.questions.length === 0) {
    return (
      <div className="page play-shell">
        <ErrorBox error={{ message: 'Ce quiz ne contient aucune question.' }} />
      </div>
    )
  }

  const isLast = index === quiz.questions.length - 1
  const selected = answers[question.id]

  return (
    <div className="page play-shell">
      <header className="page-head" style={{ marginBottom: '1rem' }}>
        <div>
          <span className={`badge ${quiz.difficulty}`}>{quiz.difficulty}</span>
          <h1 style={{ marginTop: '0.5rem' }}>{quiz.title}</h1>
        </div>
        <Link className="btn ghost sm" to="/">
          Quitter
        </Link>
      </header>

      <div className="card">
        <div className="row" style={{ justifyContent: 'space-between', marginBottom: '0.6rem' }}>
          <span className="meta" style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
            Question {index + 1} sur {quiz.questions.length}
          </span>
          <span className="meta" style={{ color: 'var(--text-faint)', fontSize: '0.85rem' }}>
            {Object.keys(answers).length} répondue(s)
          </span>
        </div>

        <div className="progress">
          <span style={{ width: `${progress}%` }} />
        </div>

        {question.image && <img className="question-image" src={assetUrl(question.image)} alt="" />}

        <p className="question-text">{question.text}</p>

        <div className="tiles">
          {question.choices.map((choice, choiceIndex) => (
            <Tile
              key={choiceIndex}
              index={choiceIndex}
              className={selected === choiceIndex ? 'selected' : ''}
              onClick={() => choose(choiceIndex)}
            >
              <span className="label">{choice}</span>
            </Tile>
          ))}
        </div>

        <ErrorBox error={error} />

        <div className="row" style={{ marginTop: '1.5rem' }}>
          <button
            type="button"
            className="btn ghost"
            onClick={() => setIndex((value) => value - 1)}
            disabled={index === 0}
          >
            Précédent
          </button>
          <span className="spacer" />
          {isLast ? (
            <button type="button" className="btn primary" onClick={finish} disabled={sending}>
              {sending ? 'Correction…' : 'Terminer le quiz'}
            </button>
          ) : (
            <button type="button" className="btn primary" onClick={() => setIndex((value) => value + 1)}>
              Suivant
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
