import { useEffect, useState } from 'react'
import { api } from '../api'
import UserPicker from './UserPicker'
import { ErrorBox, formatDate } from './ui'

const ROLE_LABELS = { prof: 'professeur', admin: 'administrateur' }

/** Création et révocation des clés d'accès, réservées à l'administration. */
export default function AccessKeys({ users }) {
  const [keys, setKeys] = useState([])
  const [role, setRole] = useState('prof')
  const [label, setLabel] = useState('')
  const [assignTo, setAssignTo] = useState(null)
  const [busy, setBusy] = useState(false)
  const [copied, setCopied] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api('/api/access-keys').then(setKeys).catch(setError)
  }, [])

  async function create(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      const key = await api('/api/access-keys', {
        method: 'POST',
        body: { role, label: label.trim() || null, userId: assignTo },
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
    if (!confirm(`Révoquer la clé ${key.value} ? Elle ne permettra plus de se connecter.`)) return

    try {
      const updated = await api(`/api/access-keys/${key.id}`, { method: 'DELETE' })
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
        <select value={role} onChange={(e) => setRole(e.target.value)} style={{ width: 'auto', flex: '0 1 180px' }}>
          <option value="prof">Clé professeur</option>
          <option value="admin">Clé administrateur</option>
        </select>
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
              <th>Rôle</th>
              <th>Étiquette / attribution</th>
              <th>Utilisations</th>
              <th>Créée le</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {keys.map((key) => (
              <tr key={key.id} style={key.active || key.assignedTo ? undefined : { opacity: 0.45 }}>
                <td>
                  <code style={{ fontSize: '0.88rem', letterSpacing: '0.04em' }}>{key.value}</code>
                </td>
                <td>
                  <span className={`badge ${key.role === 'admin' ? 'accent' : ''}`}>
                    {ROLE_LABELS[key.role] ?? key.role}
                  </span>
                </td>
                <td style={{ color: 'var(--text-muted)' }}>
                  {key.assignedTo ? <>Attribuée à <b style={{ color: 'var(--text)' }}>{key.assignedTo}</b></> : (key.label ?? '—')}
                </td>
                <td style={{ color: 'var(--text-muted)' }}>{key.usageCount}</td>
                <td style={{ color: 'var(--text-muted)' }}>{formatDate(key.createdAt)}</td>
                <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                  {key.assignedTo ? (
                    <span className="badge accent">attribuée</span>
                  ) : key.active ? (
                    <>
                      <button type="button" className="btn ghost sm" onClick={() => copy(key.value)}>
                        {copied === key.value ? 'Copiée ✓' : 'Copier'}
                      </button>
                      <button type="button" className="btn danger sm" onClick={() => revoke(key)}>
                        Révoquer
                      </button>
                    </>
                  ) : (
                    <span className="badge">révoquée</span>
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
