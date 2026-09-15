import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../api'
import { Icon } from './Icon'

/** Sérialise les filtres en query string, en ignorant ceux laissés vides. */
export function toQuery(filters) {
  const params = new URLSearchParams()

  for (const [key, value] of Object.entries(filters)) {
    if (value !== null && value !== undefined && value !== '') params.set(key, value)
  }

  const search = params.toString()
  return search ? `?${search}` : ''
}

/**
 * Liste rechargée par le serveur à chaque changement de filtre. Le filtrage se fait
 * en base, pas dans le navigateur : l'écran tient toujours quand la table grossit.
 * Un court délai évite une requête par frappe dans le champ de recherche.
 */
export function useFilteredList(path, filters, { delay = 250 } = {}) {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [nonce, setNonce] = useState(0)
  const search = toQuery(filters)
  // Le tout premier chargement ne doit pas attendre : le délai ne sert qu'à la frappe.
  const immediate = useRef(true)

  useEffect(() => {
    let cancelled = false

    const wait = immediate.current ? 0 : delay
    immediate.current = false

    // Le voyant d'attente ne s'allume qu'au départ de la requête : pendant le
    // délai de frappe, le tableau garde ses lignes au lieu de clignoter.
    const timer = setTimeout(() => {
      setLoading(true)
      api(path + search)
        .then((data) => {
          if (cancelled) return
          setRows(data)
          setError(null)
        })
        .catch((err) => !cancelled && setError(err))
        .finally(() => !cancelled && setLoading(false))
    }, wait)

    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [path, search, delay, nonce])

  /** Remplace une ligne sur place : une révocation ne doit pas faire sauter le tableau. */
  const replace = useCallback((row) => {
    setRows((current) => current.map((item) => (item.id === row.id ? row : item)))
  }, [])

  const reload = useCallback(() => setNonce((value) => value + 1), [])

  return { rows, loading, error, setError, replace, reload }
}

export function Toolbar({ children, count, total }) {
  return (
    <div className="toolbar">
      <div className="toolbar-filters">{children}</div>
      {count !== undefined && (
        <span className="toolbar-count">
          {count} {count > 1 ? 'résultats' : 'résultat'}
          {total !== undefined && total !== count && <> sur {total}</>}
        </span>
      )}
    </div>
  )
}

export function SearchInput({ value, onChange, placeholder = 'Rechercher…' }) {
  return (
    <div className="search-input">
      <Icon name="search" size={16} />
      <input type="search" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} />
    </div>
  )
}

/** Filtre déroulant ; la première option, de valeur vide, veut dire « pas de filtre ». */
export function FilterSelect({ label, value, onChange, options }) {
  return (
    <label className="filter-select">
      <span>{label}</span>
      <select value={value} onChange={(e) => onChange(e.target.value)}>
        {options.map(([option, text]) => (
          <option key={option} value={option}>
            {text}
          </option>
        ))}
      </select>
    </label>
  )
}

export function ResetFilters({ active, onReset }) {
  if (!active) return null

  return (
    <button type="button" className="btn ghost sm" onClick={onReset}>
      Réinitialiser
    </button>
  )
}

/**
 * Tableau du back-office. `columns` décrit les colonnes une fois ; chaque page
 * ne s'occupe donc que de ses données et du rendu d'une cellule.
 */
export function DataTable({ columns, rows, loading, empty, rowKey = (row) => row.id, rowClass }) {
  if (loading && !rows.length) return <TableSkeleton columns={columns.length} />

  if (!rows.length) {
    return <p className="table-empty">{empty}</p>
  }

  return (
    <div className="table-wrap" aria-busy={loading}>
      <table className="data-table">
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column.key} style={column.align ? { textAlign: column.align } : undefined}>
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={rowKey(row)} className={rowClass?.(row)}>
              {columns.map((column) => (
                <td key={column.key} style={column.align ? { textAlign: column.align } : undefined}>
                  {column.cell(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function TableSkeleton({ columns }) {
  return (
    <div className="table-wrap">
      <table className="data-table">
        <tbody>
          {Array.from({ length: 4 }, (_, line) => (
            <tr key={line}>
              {Array.from({ length: columns }, (_, cell) => (
                <td key={cell}>
                  <span className="bar-skeleton" />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

const STATUS_TONES = {
  active: 'ok',
  assigned: 'info',
  unclaimed: 'info',
  expired: 'warn',
  exhausted: 'warn',
  revoked: 'off',
}

const STATUS_LABELS = {
  active: 'active',
  assigned: 'attribuée',
  unclaimed: 'à distribuer',
  expired: 'périmée',
  exhausted: 'épuisée',
  revoked: 'révoquée',
}

export function StatusBadge({ status }) {
  return <span className={`status status-${STATUS_TONES[status] ?? 'off'}`}>{STATUS_LABELS[status] ?? status}</span>
}

/** Le code d'une clé se recopie à la main très mal : le bouton évite les fautes de frappe. */
export function CopyButton({ value, label = 'Copier' }) {
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    if (!copied) return
    const timer = setTimeout(() => setCopied(false), 1500)
    return () => clearTimeout(timer)
  }, [copied])

  async function copy() {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
    } catch {
      // Presse-papiers refusé par le navigateur : la clé reste sélectionnable à la main.
    }
  }

  return (
    <button type="button" className="btn ghost sm" onClick={copy}>
      {copied ? <>Copiée <Icon name="check" size={14} strokeWidth={2.5} /></> : label}
    </button>
  )
}

export function KeyValue({ value }) {
  return <code className="key-value">{value}</code>
}

/**
 * Une clé fraîchement émise ne se réaffiche pas en clair de façon commode une fois
 * noyée dans le tableau : on la met en avant tout de suite, prête à être copiée.
 */
export function FreshKey({ keyValue, children, onDismiss }) {
  if (!keyValue) return null

  return (
    <div className="fresh-key">
      <div>
        <strong>Clé créée</strong>
        <p>{children}</p>
      </div>
      <KeyValue value={keyValue} />
      <CopyButton value={keyValue} />
      <button type="button" className="btn ghost sm" onClick={onDismiss} aria-label="Masquer la clé créée">
        <Icon name="x" size={16} />
      </button>
    </div>
  )
}

/** En-tête d'une section du back-office : ce qu'on y fait, et l'action principale. */
export function SectionHead({ title, children, action }) {
  return (
    <header className="section-head">
      <div>
        <h1>{title}</h1>
        <p>{children}</p>
      </div>
      {action}
    </header>
  )
}

export function relativeDate(iso) {
  if (!iso) return '—'

  const days = Math.round((new Date(iso) - Date.now()) / 86_400_000)

  if (days === 0) return "aujourd'hui"
  if (days === 1) return 'demain'
  if (days === -1) return 'hier'
  return days > 0 ? `dans ${days} jours` : `il y a ${-days} jours`
}
