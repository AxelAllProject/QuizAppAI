import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api'
import { ErrorBox } from './ui'

/** Saisie du code PIN d'une partie en direct : page Rejoindre et accueil. */
export default function JoinForm({ initialPin = '', className = 'stack', style, children }) {
  const navigate = useNavigate()
  const [pin, setPin] = useState(initialPin)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      await api(`/api/live-games/${pin}/join`, { method: 'POST' })
      navigate(`/live/${pin}`)
    } catch (err) {
      setError(err)
      setBusy(false)
    }
  }

  return (
    <form className={className} style={style} onSubmit={submit}>
      {children}

      <ErrorBox error={error} />

      <input
        className="pin-input"
        value={pin}
        onChange={(e) => setPin(e.target.value.replace(/\D/g, '').slice(0, 6))}
        inputMode="numeric"
        autoComplete="off"
        placeholder="000000"
        aria-label="Code PIN"
      />

      <button className="btn primary block" type="submit" disabled={busy || pin.length !== 6}>
        {busy ? 'Connexion…' : 'Rejoindre la partie'}
      </button>
    </form>
  )
}
