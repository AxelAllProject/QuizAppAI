import { useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth'
import { ErrorBox, Field } from '../components/ui'

const EMPTY = { email: '', name: '', password: '', consent: false, accessKey: '' }

export default function Login() {
  const { user, login, register } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [mode, setMode] = useState('login')
  const [form, setForm] = useState(EMPTY)
  const [showKey, setShowKey] = useState(false)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to={location.state?.from ?? '/'} replace />

  const registering = mode === 'register'
  const set = (field) => (event) =>
    setForm({ ...form, [field]: event.target.type === 'checkbox' ? event.target.checked : event.target.value })

  function switchMode(next) {
    setMode(next)
    setError(null)
  }

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      if (registering) {
        await register({
          email: form.email.trim(),
          name: form.name.trim(),
          password: form.password,
          consent: form.consent,
          accessKey: form.accessKey.trim() || null,
        })
      } else {
        await login(form.email.trim(), form.password)
      }
      navigate(location.state?.from ?? '/', { replace: true })
    } catch (err) {
      setError(err)
      setBusy(false)
    }
  }

  const ready = registering
    ? form.email && form.name.trim().length >= 2 && form.password.length >= 8 && form.consent
    : form.email && form.password

  return (
    <div className="auth-shell">
      <form className="card auth-card stack" onSubmit={submit}>
        <div className="brand" style={{ marginBottom: '0.5rem' }}>
          <span className="brand-mark">Q</span> QuizLab
        </div>

        {location.state?.deleted && (
          <div className="notice">Ton compte et tes données ont bien été supprimés.</div>
        )}

        <div className="tabs" role="tablist">
          <button type="button" role="tab" aria-selected={!registering} className={registering ? '' : 'active'} onClick={() => switchMode('login')}>
            Connexion
          </button>
          <button type="button" role="tab" aria-selected={registering} className={registering ? 'active' : ''} onClick={() => switchMode('register')}>
            Créer un compte
          </button>
        </div>

        <p className="lede" style={{ marginBottom: 0 }}>
          {registering
            ? 'Un e-mail et un mot de passe suffisent. Pas de publicité, pas de pistage.'
            : 'Content de te revoir ! Connecte-toi pour jouer ou rejoindre une partie en direct.'}
        </p>

        <ErrorBox error={error} />

        <Field label="Adresse e-mail">
          <input type="email" value={form.email} onChange={set('email')} autoComplete="email" autoFocus maxLength={180} />
        </Field>

        {registering && (
          <Field label="Pseudo" hint="Visible par les autres joueurs dans les classements.">
            <input value={form.name} onChange={set('name')} placeholder="ex. axel" autoComplete="nickname" maxLength={40} />
          </Field>
        )}

        <Field label="Mot de passe" hint={registering ? 'Au moins 8 caractères.' : undefined}>
          <input
            type="password"
            value={form.password}
            onChange={set('password')}
            autoComplete={registering ? 'new-password' : 'current-password'}
          />
        </Field>

        {registering &&
          (showKey ? (
            <Field label="Clé d’accès" hint="Remise par l’établissement : professeur (création de quiz) ou administrateur.">
              <input
                value={form.accessKey}
                onChange={set('accessKey')}
                placeholder="XXXX-XXXX-XXXX-XXXX"
                autoComplete="off"
                spellCheck={false}
                style={{ letterSpacing: '0.06em' }}
              />
            </Field>
          ) : (
            <button type="button" className="btn ghost sm" onClick={() => setShowKey(true)}>
              J’ai une clé d’accès (professeur ou admin)
            </button>
          ))}

        {registering && (
          <label className="check">
            <input type="checkbox" checked={form.consent} onChange={set('consent')} />
            <span>
              J’ai lu la{' '}
              <Link to="/confidentialite" target="_blank" rel="noreferrer">
                politique de confidentialité
              </Link>{' '}
              et j’accepte que mes données soient utilisées pour faire fonctionner QuizLab. Si j’ai moins
              de 15 ans, un parent est d’accord.
            </span>
          </label>
        )}

        <button className="btn primary block" type="submit" disabled={busy || !ready}>
          {busy ? 'Un instant…' : registering ? 'Créer mon compte' : 'Se connecter'}
        </button>

        <Link to="/confidentialite" className="hint" style={{ textAlign: 'center', fontSize: '0.8rem', color: 'var(--text-faint)' }}>
          Confidentialité et données personnelles
        </Link>
      </form>
    </div>
  )
}
