import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { Icon } from './Icon'

const CHOICE_COUNTS = [2, 3, 4, 5, 6]

const WELCOME = {
  id: 'welcome',
  role: 'assistant',
  kind: 'info',
  text: 'Décris le quiz que tu veux (le sujet suffit) : je le rédige et je le publie directement dans la bibliothèque, prêt à jouer.',
}

/**
 * Bulle de chat flottante (façon widget de support), disponible sur toutes les pages
 * pour les professeurs et administrateurs : décrire un sujet suffit, le quiz est
 * rédigé par l'IA et publié tout de suite — un aller-retour par message, sans
 * étape manuelle. Reste ouverte et garde son fil de discussion en changeant de page.
 */
export default function AiChatWidget() {
  const [open, setOpen] = useState(false)
  const [messages, setMessages] = useState([WELCOME])
  const [topic, setTopic] = useState('')
  const [questionCount, setQuestionCount] = useState(5)
  const [choiceCount, setChoiceCount] = useState(4)
  const [difficulty, setDifficulty] = useState('moyen')
  const [busy, setBusy] = useState(false)
  const scrollRef = useRef(null)

  useEffect(() => {
    if (open) scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' })
  }, [messages, open])

  async function send(askedTopic) {
    const question = askedTopic.trim()
    if (!question || busy) return

    setBusy(true)
    setTopic('')
    setMessages((current) => [
      ...current,
      { id: crypto.randomUUID(), role: 'user', text: question },
      { id: 'typing', role: 'assistant', kind: 'typing' },
    ])

    try {
      const quiz = await api('/api/ai/quizzes', {
        method: 'POST',
        body: { topic: question, questionCount, choiceCount, difficulty },
      })
      replaceTyping({ id: crypto.randomUUID(), role: 'assistant', kind: 'created', quiz })
    } catch (err) {
      replaceTyping({ id: crypto.randomUUID(), role: 'assistant', kind: 'error', text: err.message, status: err.status, topic: question })
    } finally {
      setBusy(false)
    }
  }

  function replaceTyping(message) {
    setMessages((current) => [...current.filter((m) => m.id !== 'typing'), message])
  }

  async function remove(message) {
    if (!confirm(`Supprimer « ${message.quiz.title} » ? Cette action est définitive.`)) return

    try {
      await api(`/api/quizzes/${message.quiz.id}`, { method: 'DELETE' })
      setMessages((current) => current.map((m) => (m.id === message.id ? { ...m, deleted: true } : m)))
    } catch {
      // Le quiz reste affiché : l'échec de suppression n'empêche pas de continuer la conversation.
    }
  }

  return (
    <div className="ai-widget">
      {open && (
        <div className="ai-widget-panel card">
          <div className="row" style={{ justifyContent: 'space-between' }}>
            <h2 style={{ fontSize: '1rem' }}><Icon name="sparkles" size={18} /> Créer un quiz</h2>
            <button type="button" className="btn ghost sm" onClick={() => setOpen(false)} aria-label="Fermer">
              <Icon name="x" size={16} />
            </button>
          </div>

          <div className="ai-thread" ref={scrollRef}>
            {messages.map((message) => (
              <AiMessage key={message.id} message={message} onRetry={send} onRemove={remove} />
            ))}
          </div>

          <div className="ai-options">
            <label>
              Questions
              <input
                type="number"
                min={1}
                max={20}
                value={questionCount}
                onChange={(e) => setQuestionCount(Number(e.target.value))}
              />
            </label>
            <label>
              Réponses
              <select value={choiceCount} onChange={(e) => setChoiceCount(Number(e.target.value))}>
                {CHOICE_COUNTS.map((count) => (
                  <option key={count} value={count}>
                    {count}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Difficulté
              <select value={difficulty} onChange={(e) => setDifficulty(e.target.value)}>
                <option value="facile">Facile</option>
                <option value="moyen">Moyen</option>
                <option value="difficile">Difficile</option>
              </select>
            </label>
          </div>

          <form
            className="ai-composer"
            onSubmit={(e) => {
              e.preventDefault()
              send(topic)
            }}
          >
            <input
              value={topic}
              onChange={(e) => setTopic(e.target.value)}
              placeholder="ex. Le cycle de l’eau…"
              maxLength={200}
              autoFocus
            />
            <button className="btn primary" type="submit" disabled={busy || !topic.trim()}>
              {busy ? '…' : 'Créer'}
            </button>
          </form>
        </div>
      )}

      <button type="button" className="ai-bubble-toggle" onClick={() => setOpen((value) => !value)}>
        <Icon name={open ? 'x' : 'sparkles'} size={18} />
        <span>{open ? 'Fermer' : 'Créer un quiz'}</span>
      </button>
    </div>
  )
}

function AiMessage({ message, onRetry, onRemove }) {
  if ('typing' === message.kind) {
    return (
      <div className="ai-bubble assistant typing">
        <span />
        <span />
        <span />
      </div>
    )
  }

  if ('error' === message.kind) {
    return (
      <div className="ai-bubble assistant error">
        <p>{message.text}</p>
        <div className="row">
          {402 === message.status ? (
            <Link className="btn primary sm" to="/account">
              Mon compte
            </Link>
          ) : (
            <button type="button" className="btn ghost sm" onClick={() => onRetry(message.topic)}>
              Réessayer
            </button>
          )}
        </div>
      </div>
    )
  }

  if ('created' === message.kind) {
    const { quiz } = message

    if (message.deleted) {
      return (
        <div className="ai-bubble assistant">
          <p>« {quiz.title} » a été supprimé.</p>
        </div>
      )
    }

    return (
      <div className="ai-bubble assistant">
        <p>
          C’est fait ! J’ai créé et publié <b>{quiz.title}</b> — {quiz.questions.length} question
          {quiz.questions.length > 1 ? 's' : ''}, catégorie {quiz.category}, niveau {quiz.difficulty}.
        </p>
        {undefined !== quiz.aiGenerationsLeft && (
          <p style={{ color: 'var(--text-faint)', fontSize: '0.8rem' }}>
            {quiz.aiGenerationsLeft} génération{quiz.aiGenerationsLeft > 1 ? 's' : ''} restante
            {quiz.aiGenerationsLeft > 1 ? 's' : ''}.
          </p>
        )}
        <ol className="ai-preview">
          {quiz.questions.map((question) => (
            <li key={question.id}>{question.text}</li>
          ))}
        </ol>
        <div className="row">
          <Link className="btn primary sm" to={`/quiz/${quiz.id}`}>
            Jouer
          </Link>
          <Link className="btn ghost sm" to={`/quiz/${quiz.id}/edit`}>
            Modifier
          </Link>
          <button type="button" className="btn danger sm" onClick={() => onRemove(message)}>
            Supprimer
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className={`ai-bubble ${message.role}${'info' === message.kind ? ' info' : ''}`}>
      <p>{message.text}</p>
    </div>
  )
}
