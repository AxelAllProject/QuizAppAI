import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../api'
import { ErrorBox } from '../components/ui'

export default function Join() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [pin, setPin] = useState(params.get('pin') ?? '')
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
    <div className="page play-shell">
      <form className="card stack" onSubmit={submit} style={{ maxWidth: 440, margin: '2rem auto', textAlign: 'center' }}>
        <h1>Rejoindre une partie</h1>
        <p style={{ color: 'var(--text-muted)' }}>Saisis le code PIN affiché sur l’écran de l’animateur.</p>

        <ErrorBox error={error} />

        <input
          className="pin-input"
          value={pin}
          onChange={(e) => setPin(e.target.value.replace(/\D/g, '').slice(0, 6))}
          inputMode="numeric"
          autoComplete="off"
          placeholder="000000"
          aria-label="Code PIN"
          autoFocus
        />

        <button className="btn primary block" type="submit" disabled={busy || pin.length !== 6}>
          {busy ? 'Connexion…' : 'Entrer'}
        </button>
      </form>
    </div>
  )
}
