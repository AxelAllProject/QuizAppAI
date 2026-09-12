import { useEffect, useState } from 'react'
import { api } from '../api'
import UserPicker from './UserPicker'
import { ErrorBox, formatDate } from './ui'

/** Création et révocation des clés IA : la génération de quiz est une fonctionnalité premium à part du rôle. */
export default function AiKeys({ users }) {
  const [keys, setKeys] = useState([])
  const [totalGenerations, setTotalGenerations] = useState(5)
  const [expiresInDays, setExpiresInDays] = useState('')
  const [label, setLabel] = useState('')
  const [assignTo, setAssignTo] = useState(null)
  const [busy, setBusy] = useState(false)
  const [copied, setCopied] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api('/api/ai-keys').then(setKeys).catch(setError)
  }, [])

  async function create(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      const key = await api('/api/ai-keys', {
        method: 'POST',
        body: {
          totalGenerations,
          label: label.trim() || null,
          expiresInDays: expiresInDays ? Number(expiresInDays) : null,
          userId: assignTo,
        },
      })
      setKeys((current) => [key, ...current])
      setLabel('')
      setAssignTo(null)
    } catch (err) {
      setError(err)
    } finally {
      setBusy(false)
    }
  }

  async function revoke(key) {
    if (!confirm(`Révoquer la clé ${key.value} ? Elle ne débloquera plus la génération.`)) return

    try {
      const updated = await api(`/api/ai-keys/${key.id}`, { method: 'DELETE' })
      setKeys((current) => current.map((item) => (item.id === key.id ? updated : item)))
    } catch (err) {
      setError(err)
    }
  }

  async function copy(value) {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(value)
      setTimeout(() => setCopied(null), 1500)
    } catch {
      // Presse-papiers refusé par le navigateur : la clé reste sélectionnable à la main.
    }
  }

  return (
    <div className="card" style={{ marginBottom: '1.5rem' }}>
      <form className="row" onSubmit={create} style={{ marginBottom: keys.length ? '1.25rem' : 0 }}>
        <label className="row" style={{ gap: '0.4rem', flex: '0 1 auto' }}>
          <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>Générations</span>
          <input
            type="number"
            min={1}
            max={1000}
            value={totalGenerations}
            onChange={(e) => setTotalGenerations(Number(e.target.value))}
            style={{ width: '5rem' }}
          />
        </label>
        <label className="row" style={{ gap: '0.4rem', flex: '0 1 auto' }}>
          <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>Expire dans (jours)</span>
          <input
            type="number"
            min={1}
            max={365}
            placeholder="jamais"
            value={expiresInDays}
            onChange={(e) => setExpiresInDays(e.target.value)}
            style={{ width: '6rem' }}
          />
        </label>
        <UserPicker users={users} value={assignTo} onChange={setAssignTo} placeholder="Attribuer à un compte…" />
        {!assignTo && (
          <input
            value={label}
            onChange={(e) => setLabel(e.target.value)}
            placeholder="À qui est-elle remise ? (facultatif)"
            maxLength={120}
            style={{ flex: '1 1 200px', width: 'auto' }}
          />
        )}
        <button className="btn primary" type="submit" disabled={busy}>
          {busy ? 'Génération…' : assignTo ? 'Attribuer' : 'Générer une clé'}
        </button>
      </form>

      <ErrorBox error={error} />

      {keys.length > 0 && (
        <table>
          <thead>
            <tr>
              <th>Clé</th>
              <th>Étiquette</th>
              <th>Générations</th>
              <th>Détenteur</th>
              <th>Expire le</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {keys.map((key) => (
              <tr key={key.id} style={key.active ? undefined : { opacity: 0.45 }}>
                <td>
                  <code style={{ fontSize: '0.88rem', letterSpacing: '0.04em' }}>{key.value}</code>
                </td>
                <td style={{ color: 'var(--text-muted)' }}>{key.label ?? '—'}</td>
                <td>
                  <span className={`badge ${key.remainingGenerations > 0 ? 'accent' : ''}`}>
                    {key.remainingGenerations} / {key.totalGenerations}
                  </span>
                </td>
                <td style={{ color: 'var(--text-muted)' }}>{key.redeemedBy ?? '—'}</td>
                <td style={{ color: key.expired ? 'var(--danger)' : 'var(--text-muted)' }}>
                  {key.expiresAt ? formatDate(key.expiresAt) : 'jamais'}
                </td>
                <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                  {key.revokedAt ? (
                    <span className="badge">révoquée</span>
                  ) : (
                    <>
                      <button type="button" className="btn ghost sm" onClick={() => copy(key.value)}>
                        {copied === key.value ? 'Copiée ✓' : 'Copier'}
                      </button>
                      <button type="button" className="btn danger sm" onClick={() => revoke(key)}>
                        Révoquer
                      </button>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}
