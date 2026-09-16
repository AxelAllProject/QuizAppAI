import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api'
import { useAuth } from '../auth'
import AiChatWidget from '../components/AiChatWidget'
import ImageField from '../components/ImageField'
import { ErrorBox, Field } from '../components/ui'
import { QUIZ_LIMITS } from '../limits'
import { uid } from '../uid'
import { Icon } from '../components/Icon'

/** Durées de chrono proposées par question, en secondes. */
const TIME_LIMITS = [5, 10, 20, 30, 60, 90, 120, 240]

/** Crée une question vide avec deux propositions. */
const emptyQuestion = () => ({
  key: uid(),
  text: '',
  image: null,
  choices: ['', ''],
  correctIndex: 0,
  timeLimit: 20,
  explanation: '',
})

/** Éditeur de quiz : création ou modification d'un quiz et de ses questions. */
export default function Editor() {
  const { id } = useParams()
  const navigate = useNavigate()
  const editing = Boolean(id)
  const { canCreate } = useAuth()

  const [form, setForm] = useState({
    title: '',
    description: '',
    category: '',
    difficulty: 'moyen',
    coverImage: null,
  })
  const [questions, setQuestions] = useState([emptyQuestion()])
  const [error, setError] = useState(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!editing) return

    api(`/api/quizzes/${id}?withAnswers=1`)
      .then((quiz) => {
        setForm({
          title: quiz.title,
          description: quiz.description ?? '',
          category: quiz.category,
          difficulty: quiz.difficulty,
          coverImage: quiz.coverImage ?? null,
        })
        setQuestions(
          quiz.questions.map((question) => ({
            key: uid(),
            text: question.text,
            image: question.image ?? null,
            choices: question.choices,
            correctIndex: question.correctIndex ?? 0,
            timeLimit: question.timeLimit ?? 20,
            explanation: question.explanation ?? '',
          })),
        )
      })
      .catch(setError)
  }, [id, editing])

  /** Modifie un ou plusieurs champs d'une question. */
  function updateQuestion(key, patch) {
    setQuestions((current) =>
      current.map((question) => (question.key === key ? { ...question, ...patch } : question)),
    )
  }

  /** Modifie le texte d'une proposition. */
  function updateChoice(key, index, value) {
    setQuestions((current) =>
      current.map((question) =>
        question.key === key
          ? { ...question, choices: question.choices.map((c, i) => (i === index ? value : c)) }
          : question,
      ),
    )
  }

  /** Ajoute une proposition vide (6 au maximum). */
  function addChoice(key) {
    setQuestions((current) =>
      current.map((question) =>
        question.key === key && question.choices.length < QUIZ_LIMITS.choices
          ? { ...question, choices: [...question.choices, ''] }
          : question,
      ),
    )
  }

  /** Retire une proposition (2 au minimum) en gardant la bonne réponse cohérente. */
  function removeChoice(key, index) {
    setQuestions((current) =>
      current.map((question) => {
        if (question.key !== key || question.choices.length <= 2) return question

        const choices = question.choices.filter((_, i) => i !== index)
        // La bonne réponse suit le décalage, sans jamais sortir des bornes.
        const correctIndex =
          question.correctIndex === index
            ? 0
            : question.correctIndex > index
              ? question.correctIndex - 1
              : question.correctIndex

        return { ...question, choices, correctIndex }
      }),
    )
  }

  /** Déplace une question vers le haut ou vers le bas. */
  function moveQuestion(position, offset) {
    setQuestions((current) => {
      const next = [...current]
      const [moved] = next.splice(position, 1)
      next.splice(position + offset, 0, moved)
      return next
    })
  }

  /** Enregistre le quiz (création ou modification), puis ouvre la bibliothèque. */
  async function save(event) {
    event.preventDefault()
    setSaving(true)
    setError(null)

    const body = {
      ...form,
      description: form.description || null,
      category: form.category || 'Général',
      questions: questions.map(({ text, image, choices, correctIndex, timeLimit, explanation }) => ({
        text,
        image,
        choices,
        correctIndex,
        timeLimit,
        explanation: explanation || null,
      })),
    }

    try {
      const quiz = editing
        ? await api(`/api/quizzes/${id}`, { method: 'PUT', body })
        : await api('/api/quizzes', { method: 'POST', body })
      navigate(`/quiz/${quiz.id}`)
    } catch (err) {
      setError(err)
      setSaving(false)
    }
  }

  return (
    <>
      <form className="page" onSubmit={save}>
        <header className="page-head">
          <div>
            <h1>{editing ? 'Modifier le quiz' : 'Créer un quiz'}</h1>
            <p>Ajoute tes questions et tes images, coche la bonne réponse, règle le chrono, et publie.</p>
          </div>
          <div className="row">
            <button type="button" className="btn ghost" onClick={() => navigate(-1)}>
              Annuler
            </button>
            <button className="btn primary" type="submit" disabled={saving}>
              {saving ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Publier le quiz'}
            </button>
          </div>
        </header>

        <ErrorBox error={error} />

        <div className="card stack" style={{ margin: '1rem 0 1.5rem' }}>
          <Field label="Titre">
            <input
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
              placeholder="ex. Les capitales du monde"
              maxLength={QUIZ_LIMITS.title}
            />
          </Field>

          <Field label="Description" hint="Facultatif — une phrase pour situer le sujet.">
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="De quoi parle ce quiz ?"
              maxLength={QUIZ_LIMITS.description}
            />
          </Field>

          <div className="form-grid">
            <Field label="Catégorie">
              <input
                value={form.category}
                onChange={(e) => setForm({ ...form, category: e.target.value })}
                placeholder="Général"
                maxLength={QUIZ_LIMITS.category}
              />
            </Field>
            <Field label="Difficulté">
              <select
                value={form.difficulty}
                onChange={(e) => setForm({ ...form, difficulty: e.target.value })}
              >
                <option value="facile">Facile</option>
                <option value="moyen">Moyen</option>
                <option value="difficile">Difficile</option>
              </select>
            </Field>
          </div>

          <Field label="Image de couverture" hint="Facultatif — affichée dans la bibliothèque et au lancement d’une partie en direct.">
            <ImageField
              value={form.coverImage}
              onChange={(coverImage) => setForm((current) => ({ ...current, coverImage }))}
              label="Ajouter une couverture"
            />
          </Field>
        </div>

        <div className="page-head" style={{ marginBottom: '1rem' }}>
          <h2>
            Questions <span className="badge accent">{questions.length}</span>
          </h2>
          <button
            type="button"
            className="btn sm"
            onClick={() => setQuestions([...questions, emptyQuestion()])}
            disabled={questions.length >= QUIZ_LIMITS.questions}
            title={questions.length >= QUIZ_LIMITS.questions ? `${QUIZ_LIMITS.questions} questions au maximum par quiz` : undefined}
          >
            + Ajouter une question
          </button>
        </div>

        <div className="stack">
          {questions.map((question, position) => (
            <div className="question-editor" key={question.key}>
              <header>
                <span className="n">Question {position + 1}</span>
                <span className="spacer" />
                <button
                  type="button"
                  className="btn ghost sm"
                  onClick={() => moveQuestion(position, -1)}
                  disabled={position === 0}
                  aria-label="Monter la question"
                >
                  ↑
                </button>
                <button
                  type="button"
                  className="btn ghost sm"
                  onClick={() => moveQuestion(position, 1)}
                  disabled={position === questions.length - 1}
                  aria-label="Descendre la question"
                >
                  ↓
                </button>
                {questions.length > 1 && (
                  <button
                    type="button"
                    className="btn danger sm"
                    onClick={() => setQuestions(questions.filter((q) => q.key !== question.key))}
                  >
                    Retirer
                  </button>
                )}
              </header>

              <div className="stack">
                <Field label="Intitulé">
                  <input
                    value={question.text}
                    onChange={(e) => updateQuestion(question.key, { text: e.target.value })}
                    placeholder="Quelle est la capitale de l’Italie ?"
                    maxLength={QUIZ_LIMITS.questionText}
                  />
                </Field>

                <div className="form-grid">
                  <Field label="Illustration" hint="Facultatif — affichée au-dessus de la question.">
                    <ImageField
                      value={question.image}
                      onChange={(image) => updateQuestion(question.key, { image })}
                    />
                  </Field>
                  <Field label="Chrono en partie en direct" hint="Répondre vite rapporte plus de points.">
                    <select
                      value={question.timeLimit}
                      onChange={(e) => updateQuestion(question.key, { timeLimit: Number(e.target.value) })}
                    >
                      {TIME_LIMITS.map((seconds) => (
                        <option key={seconds} value={seconds}>
                          {seconds < 60 ? `${seconds} secondes` : `${seconds / 60} min`}
                        </option>
                      ))}
                    </select>
                  </Field>
                </div>

                <Field label="Réponses" hint="Coche la bonne réponse à gauche. De 2 à 6 propositions.">
                  <div className="stack" style={{ gap: '0.5rem' }}>
                    {question.choices.map((choice, choiceIndex) => (
                      <div className="choice-row" key={choiceIndex}>
                        <input
                          type="radio"
                          name={`correct-${question.key}`}
                          checked={question.correctIndex === choiceIndex}
                          onChange={() => updateQuestion(question.key, { correctIndex: choiceIndex })}
                          aria-label={`Réponse ${choiceIndex + 1} correcte`}
                        />
                        <input
                          value={choice}
                          onChange={(e) => updateChoice(question.key, choiceIndex, e.target.value)}
                          placeholder={`Proposition ${choiceIndex + 1}`}
                          maxLength={QUIZ_LIMITS.choiceText}
                        />
                        {question.choices.length > 2 && (
                          <button
                            type="button"
                            className="btn ghost sm remove"
                            onClick={() => removeChoice(question.key, choiceIndex)}
                            aria-label="Supprimer cette proposition"
                          >
                            <Icon name="x" size={16} />
                          </button>
                        )}
                      </div>
                    ))}
                  </div>
                </Field>

                {question.choices.length < QUIZ_LIMITS.choices && (
                  <button type="button" className="btn ghost sm" onClick={() => addChoice(question.key)}>
                    + Proposition
                  </button>
                )}

                <Field label="Explication" hint="Facultatif — affichée dans la correction.">
                  <input
                    value={question.explanation}
                    onChange={(e) => updateQuestion(question.key, { explanation: e.target.value })}
                    placeholder="Pourquoi cette réponse ?"
                    maxLength={QUIZ_LIMITS.explanation}
                  />
                </Field>
              </div>
            </div>
          ))}
        </div>
      </form>

      {/* Génération par IA : seulement pour créer un quiz, et réservée aux professeurs et administrateurs. */}
      {!editing && canCreate && <AiChatWidget />}
    </>
  )
}
