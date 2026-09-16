import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, downloadJson } from '../api'
import { useAuth } from '../auth'
import { ErrorBox, Field, formatDate } from '../components/ui'
import { Icon } from '../components/Icon'

/** Libellé affiché pour chaque rôle. */
const ROLE_LABELS = { user: 'élève', prof: 'professeur', admin: 'administrateur' }

/** Page « Mon compte » : clés, export et suppression des données. */
export default function Account() {
  const { user, isAdmin, canCreate, updateUser, endSession } = useAuth()
  const navigate = useNavigate()

  const [key, setKey] = useState('')
  const [keyState, setKeyState] = useState({ busy: false, error: null, done: false })
  const [aiKey, setAiKey] = useState('')
  const [aiKeyState, setAiKeyState] = useState({ busy: false, error: null, done: false })
  const [exportError, setExportError] = useState(null)
  const [password, setPassword] = useState('')
  const [deleteState, setDeleteState] = useState({ busy: false, error: null })

  // Le quota de la clé IA baisse à chaque génération, faite depuis une autre page : on relit le compte.
  useEffect(() => {
    api('/api/me').then(updateUser).catch(() => {})
  }, [updateUser])

  /** Envoie la clé d'accès saisie et met à jour le rôle affiché. */
  async function redeem(event) {
    event.preventDefault()
    setKeyState({ busy: true, error: null, done: false })

    try {
      updateUser(await api('/api/me/access-key', { method: 'POST', body: { key: key.trim() } }))
      setKey('')
      setKeyState({ busy: false, error: null, done: true })
    } catch (err) {
      setKeyState({ busy: false, error: err, done: false })
    }
  }

  /** Envoie la clé IA saisie et met à jour le quota affiché. */
  async function redeemAiKey(event) {
    event.preventDefault()
    setAiKeyState({ busy: true, error: null, done: false })

    try {
      updateUser(await api('/api/me/ai-key', { method: 'POST', body: { key: aiKey.trim() } }))
      setAiKey('')
      setAiKeyState({ busy: false, error: null, done: true })
    } catch (err) {
      setAiKeyState({ busy: false, error: err, done: false })
    }
  }

  /** Télécharge toutes les données du compte en JSON. */
  async function exportData() {
    setExportError(null)
    try {
      await downloadJson('/api/me/export', 'quizlab-mes-donnees.json')
    } catch (err) {
      setExportError(err)
    }
  }

  /** Supprime le compte après confirmation et mot de passe, puis renvoie à la connexion. */
  async function remove(event) {
    event.preventDefault()
    if (!confirm('Supprimer définitivement ton compte et ton historique ? Cette action ne peut pas être annulée.')) return

    setDeleteState({ busy: true, error: null })
    try {
      await api('/api/me', { method: 'DELETE', body: { password } })
      navigate('/login', { replace: true, state: { deleted: true } })
      endSession()
    } catch (err) {
      setDeleteState({ busy: false, error: err })
    }
  }

  return (
    <div className="page play-shell">
      <header className="page-head">
        <div>
          <h1>Mon compte</h1>
          <p>Tes informations, et la main sur tes données personnelles.</p>
        </div>
      </header>

      <div className="stack">
        <section className="card">
          <h2 style={{ marginBottom: '0.75rem' }}>Mes informations</h2>
          <dl className="details">
            <dt>Pseudo</dt>
            <dd>{user.name}</dd>
            <dt>E-mail</dt>
            <dd>{user.email}</dd>
            <dt>Rôle</dt>
            <dd>{ROLE_LABELS[user.role] ?? user.role}</dd>
            {user.createdAt && (
              <>
                <dt>Inscrit le</dt>
                <dd>{formatDate(user.createdAt)}</dd>
              </>
            )}
            {user.consentedAt && (
              <>
                <dt>Politique acceptée</dt>
                <dd>
                  le {formatDate(user.consentedAt)} (version {user.privacyPolicyVersion}) —{' '}
                  <Link className="link" to="/confidentialite">
                    relire
                  </Link>
                </dd>
              </>
            )}
          </dl>
        </section>

        {!isAdmin && (
          <section className="card">
            <h2 style={{ marginBottom: '0.35rem' }}>Clé d’accès</h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '0.9rem' }}>
              Une clé professeur permet de créer des quiz et d’animer des parties en direct.
            </p>
            <form className="row" onSubmit={redeem}>
              <input
                value={key}
                onChange={(e) => setKey(e.target.value)}
                placeholder="XXXX-XXXX-XXXX-XXXX"
                autoComplete="off"
                spellCheck={false}
                style={{ flex: '1 1 220px', width: 'auto', letterSpacing: '0.06em' }}
              />
              <button className="btn primary" type="submit" disabled={keyState.busy || !key.trim()}>
                Valider la clé
              </button>
            </form>
            {keyState.done && <div className="notice" style={{ marginTop: '0.75rem' }}>Clé acceptée : ton rôle est maintenant « {ROLE_LABELS[user.role]} ».</div>}
            <div style={{ marginTop: keyState.error ? '0.75rem' : 0 }}>
              <ErrorBox error={keyState.error} />
            </div>
          </section>
        )}

        {canCreate && (
          <section className="card">
            <h2 style={{ marginBottom: '0.35rem' }}><Icon name="sparkles" size={18} /> Clé IA</h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '0.9rem' }}>
              La génération de quiz par IA est une fonctionnalité premium : elle demande une clé IA en plus du
              rôle professeur ou administrateur, remise par un administrateur.
            </p>

            {user.aiKey ? (
              <div className="notice">
                Il te reste <b>{user.aiKey.remainingGenerations}</b> génération
                {user.aiKey.remainingGenerations > 1 ? 's' : ''} sur {user.aiKey.totalGenerations}
                {user.aiKey.expiresAt && <> — valable jusqu’au {formatDate(user.aiKey.expiresAt)}</>}.
              </div>
            ) : (
              <p style={{ color: 'var(--text-faint)', fontSize: '0.85rem', marginBottom: '0.75rem' }}>
                Aucune clé IA active pour l’instant.
              </p>
            )}

            <form className="row" onSubmit={redeemAiKey} style={{ marginTop: '0.9rem' }}>
              <input
                value={aiKey}
                onChange={(e) => setAiKey(e.target.value)}
                placeholder="XXXX-XXXX-XXXX-XXXX"
                autoComplete="off"
                spellCheck={false}
                style={{ flex: '1 1 220px', width: 'auto', letterSpacing: '0.06em' }}
              />
              <button className="btn primary" type="submit" disabled={aiKeyState.busy || !aiKey.trim()}>
                Valider la clé
              </button>
            </form>
            {aiKeyState.done && <div className="notice" style={{ marginTop: '0.75rem' }}>Clé IA activée.</div>}
            <div style={{ marginTop: aiKeyState.error ? '0.75rem' : 0 }}>
              <ErrorBox error={aiKeyState.error} />
            </div>
          </section>
        )}

        <section className="card">
          <h2 style={{ marginBottom: '0.35rem' }}>Mes données</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '0.9rem' }}>
            Un fichier JSON avec tout ce que QuizLab sait de toi : compte, quiz rédigés, parties jouées et
            participations en direct.
          </p>
          <button type="button" className="btn" onClick={exportData}>
            Télécharger mes données
          </button>
          <div style={{ marginTop: exportError ? '0.75rem' : 0 }}>
            <ErrorBox error={exportError} />
          </div>
        </section>

        <section className="card danger-zone">
          <h2 style={{ marginBottom: '0.35rem' }}>Supprimer mon compte</h2>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '0.9rem' }}>
            Ton compte, ton historique et tes participations sont effacés immédiatement. Les quiz que tu as rédigés
            restent jouables, signés « compte supprimé ».
          </p>
          <form className="stack" onSubmit={remove}>
            <ErrorBox error={deleteState.error} />
            <Field label="Confirme avec ton mot de passe">
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
              />
            </Field>
            <div>
              <button className="btn danger" type="submit" disabled={deleteState.busy || !password}>
                {deleteState.busy ? 'Suppression…' : 'Supprimer définitivement'}
              </button>
            </div>
          </form>
        </section>
      </div>
    </div>
  )
}
