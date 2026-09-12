import { useMemo, useState } from 'react'

const ROLE_LABELS = { user: 'joueur', prof: 'professeur', admin: 'administrateur' }

/**
 * Filtre la liste des comptes par pseudo pour en choisir un précis — l'attribution
 * directe d'une clé à quelqu'un plutôt que de générer un code à partager.
 */
export default function UserPicker({ users, value, onChange, placeholder = 'Chercher un compte…' }) {
  const [query, setQuery] = useState('')

  const selected = users.find((account) => account.id === value)
  const matches = useMemo(() => {
    const term = query.trim().toLowerCase()
    if (!term) return []
    return users.filter((account) => account.name.toLowerCase().includes(term)).slice(0, 8)
  }, [users, query])

  if (selected) {
    return (
      <span className="badge accent user-picker-selected">
        Attribuer à {selected.name}
        <button type="button" onClick={() => onChange(null)} aria-label="Annuler l’attribution">
          ✕
        </button>
      </span>
    )
  }

  return (
    <div className="user-picker">
      <input
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder={placeholder}
        style={{ width: 'auto' }}
      />
      {matches.length > 0 && (
        <ul className="user-picker-results">
          {matches.map((account) => (
            <li key={account.id}>
              <button
                type="button"
                onClick={() => {
                  onChange(account.id)
                  setQuery('')
                }}
              >
                {account.name} <span>{ROLE_LABELS[account.role] ?? account.role}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
