import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api'
import Brand from '../components/Brand'
import { ErrorBox, Field } from '../components/ui'

/** Choix du nouveau mot de passe, depuis le lien reçu par e-mail. */
export default function ResetPassword() {
  const { token } = useParams()
  const navigate = useNavigate()
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [state, setState] = useState({ busy: false, error: null })

  const tooShort = password.length > 0 && password.length < 8
  const mismatch = confirmation.length > 0 && password !== confirmation

  /** Applique le nouveau mot de passe, puis renvoie à la connexion. */
  async function submit(event) {
    event.preventDefault()
    setState({ busy: true, error: null })

    try {
      await api('/api/password-reset/confirm', { method: 'POST', body: { token, password } })
      navigate('/login', { replace: true, state: { passwordReset: true } })
    } catch (err) {
      setState({ busy: false, error: err })
    }
  }

  return (
    <div className="auth-shell">
      <section className="card auth-card">
        <div className="brand" style={{ marginBottom: '0.5rem' }}>
          <Brand />
        </div>
        <h1>Nouveau mot de passe</h1>

        <form onSubmit={submit} className="stack">
          <p className="lede">
            Choisis un mot de passe d’au moins 8 caractères. Toutes tes autres sessions seront fermées.
          </p>

          <ErrorBox error={state.error} />

          <Field label="Nouveau mot de passe" hint={tooShort ? 'Au moins 8 caractères.' : null}>
            <input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="new-password"
              required
            />
          </Field>

          <Field label="Confirmation" hint={mismatch ? 'Les deux mots de passe diffèrent.' : null}>
            <input
              type="password"
              value={confirmation}
              onChange={(event) => setConfirmation(event.target.value)}
              autoComplete="new-password"
              required
            />
          </Field>

          <button className="btn primary" type="submit" disabled={state.busy || tooShort || mismatch || !confirmation}>
            {state.busy ? 'Enregistrement…' : 'Changer mon mot de passe'}
          </button>

          <Link className="link" to="/mot-de-passe-oublie">
            Le lien ne marche plus ? En demander un autre
          </Link>
        </form>
      </section>
    </div>
  )
}
