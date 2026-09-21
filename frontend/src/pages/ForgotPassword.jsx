import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import Brand from '../components/Brand'
import { ErrorBox, Field } from '../components/ui'
import { Icon } from '../components/Icon'

/** Demande d'un lien de réinitialisation, envoyé par e-mail. */
export default function ForgotPassword() {
  const [email, setEmail] = useState('')
  const [state, setState] = useState({ busy: false, error: null, sent: false })

  /** Demande l'envoi du lien. */
  async function submit(event) {
    event.preventDefault()
    setState({ busy: true, error: null, sent: false })

    try {
      await api('/api/password-reset', { method: 'POST', body: { email: email.trim() } })
      setState({ busy: false, error: null, sent: true })
    } catch (err) {
      setState({ busy: false, error: err, sent: false })
    }
  }

  return (
    <div className="auth-shell">
      <section className="card auth-card">
        <div className="brand" style={{ marginBottom: '0.5rem' }}>
          <Brand />
        </div>
        <h1>Mot de passe oublié</h1>

        {state.sent ? (
          <>
            {/* Le message ne dit pas si l'adresse a un compte : ce formulaire n'est pas un annuaire. */}
            <p className="lede">
              <Icon name="mail" size={16} /> Si un compte existe avec cette adresse, un lien vient d’être
              envoyé. Il est valable une heure et ne fonctionne qu’une fois.
            </p>
            <p className="hint">Pense à regarder dans les courriers indésirables.</p>
            <Link className="btn ghost" to="/login">
              Revenir à la connexion
            </Link>
          </>
        ) : (
          <form onSubmit={submit} className="stack">
            <p className="lede">
              Indique l’adresse de ton compte : tu recevras un lien pour choisir un nouveau mot de passe.
            </p>

            <ErrorBox error={state.error} />

            <Field label="Adresse e-mail">
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="email"
                required
              />
            </Field>

            <button className="btn primary" type="submit" disabled={state.busy || !email.trim()}>
              {state.busy ? 'Envoi…' : 'Envoyer le lien'}
            </button>

            <Link className="link" to="/login">
              Revenir à la connexion
            </Link>
          </form>
        )}
      </section>
    </div>
  )
}
