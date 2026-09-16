import { useEffect, useState } from 'react'
import { api } from '../../api'
import {
  CopyButton,
  DataTable,
  FilterSelect,
  FreshKey,
  KeyValue,
  ResetFilters,
  SearchInput,
  SectionHead,
  StatusBadge,
  Toolbar,
  relativeDate,
  useFilteredList,
} from '../../components/admin'
import UserPicker from '../../components/UserPicker'
import { ErrorBox, Field, formatDate } from '../../components/ui'

/** Options du filtre par état. */
const STATUS_FILTERS = [
  ['', 'Tous les états'],
  ['active', 'Active'],
  ['unclaimed', 'À distribuer'],
  ['exhausted', 'Épuisée'],
  ['expired', 'Périmée'],
  ['revoked', 'Révoquée'],
]

/** Durées de validité proposées à la création d'une clé IA. */
const DURATIONS = [
  ['', 'Sans expiration'],
  ['7', '7 jours'],
  ['30', '30 jours'],
  ['90', '90 jours'],
  ['365', '1 an'],
]

/** Filtres vides (tout afficher). */
const EMPTY = { search: '', status: '' }

/** Clés IA : la génération de quiz est une option payante, indépendante du rôle. */
export default function AiKeysPage() {
  const [filters, setFilters] = useState(EMPTY)
  const { rows, loading, error, setError, replace, reload } = useFilteredList('/api/ai-keys', filters)

  const [users, setUsers] = useState([])
  useEffect(() => {
    api('/api/users').then(setUsers).catch(setError)
  }, [setError])

  const [totalGenerations, setTotalGenerations] = useState(5)
  const [expiresInDays, setExpiresInDays] = useState('')
  const [label, setLabel] = useState('')
  const [assignTo, setAssignTo] = useState(null)
  const [busy, setBusy] = useState(false)
  const [created, setCreated] = useState(null)

  /** Crée une clé IA (ou la lie à un compte) et l'affiche en tête. */
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

      setCreated(assignTo ? null : key)
      setLabel('')
      setExpiresInDays('')
      setAssignTo(null)
      reload()
    } catch (err) {
      setError(err)
    } finally {
      setBusy(false)
    }
  }

  /** Révoque une clé IA après confirmation. */
  async function revoke(key) {
    if (!confirm(`Révoquer la clé ${key.value} ? Elle ne débloquera plus la génération.`)) return

    try {
      replace(await api(`/api/ai-keys/${key.id}`, { method: 'DELETE' }))
    } catch (err) {
      setError(err)
    }
  }

  const columns = [
    { key: 'value', header: 'Clé', cell: (key) => <KeyValue value={key.value} /> },
    { key: 'status', header: 'État', cell: (key) => <StatusBadge status={key.status} /> },
    {
      key: 'generations',
      header: 'Générations',
      cell: (key) => (
        <span className="quota">
          <span className="quota-bar">
            <span style={{ width: `${(key.remainingGenerations / key.totalGenerations) * 100}%` }} />
          </span>
          {key.remainingGenerations} / {key.totalGenerations}
        </span>
      ),
    },
    {
      key: 'holder',
      header: 'Détenteur',
      cell: (key) => (key.redeemedBy ? <b>{key.redeemedBy}</b> : <span className="muted">{key.label ?? 'non liée'}</span>),
    },
    {
      key: 'expiresAt',
      header: 'Expiration',
      cell: (key) =>
        key.expiresAt ? (
          <span className={key.expired ? 'muted danger' : undefined} title={formatDate(key.expiresAt)}>
            {relativeDate(key.expiresAt)}
          </span>
        ) : (
          <span className="muted">jamais</span>
        ),
    },
    { key: 'createdAt', header: 'Créée le', cell: (key) => <span className="muted">{formatDate(key.createdAt)}</span> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      cell: (key) =>
        key.revokedAt ? null : (
          <span className="row-actions">
            <CopyButton value={key.value} />
            <button type="button" className="btn danger sm" onClick={() => revoke(key)}>
              Révoquer
            </button>
          </span>
        ),
    },
  ]

  const filtered = filters.search !== '' || filters.status !== ''

  return (
    <>
      <SectionHead title="Clés IA">
        Avoir le rôle professeur ou administrateur ne suffit pas à générer des quiz par IA : il faut en plus une clé
        IA active. Chaque clé se lie à un compte — tout de suite si tu en choisis un, sinon au premier qui saisit le
        code — et ouvre droit à un nombre fixe de générations, qui ne se renouvelle pas.
      </SectionHead>

      <ErrorBox error={error} />

      <form className="panel create-form" onSubmit={create}>
        <Field label="Générations" hint="Total non renouvelable.">
          <input
            type="number"
            min={1}
            max={1000}
            value={totalGenerations}
            onChange={(e) => setTotalGenerations(Number(e.target.value))}
          />
        </Field>

        <Field label="Expiration" hint="Au-delà, le solde restant est perdu.">
          <select value={expiresInDays} onChange={(e) => setExpiresInDays(e.target.value)}>
            {DURATIONS.map(([days, text]) => (
              <option key={days} value={days}>
                {text}
              </option>
            ))}
          </select>
        </Field>

        <Field label="Lier à" hint="Laisse vide pour générer un code à transmettre.">
          <UserPicker users={users} value={assignTo} onChange={setAssignTo} placeholder="Chercher un compte…" />
        </Field>

        {!assignTo && (
          <Field label="Étiquette" hint="Pour se rappeler à qui le code est remis.">
            <input value={label} onChange={(e) => setLabel(e.target.value)} placeholder="Ex. Mme Martin" maxLength={120} />
          </Field>
        )}

        <button className="btn primary" type="submit" disabled={busy}>
          {busy ? 'Génération…' : assignTo ? 'Lier la clé' : 'Générer une clé'}
        </button>
      </form>

      <FreshKey keyValue={created?.value} onDismiss={() => setCreated(null)}>
        Transmets-la : elle se saisit depuis « Mon compte » et se lie au premier compte qui l’utilise.
        {created?.expiresAt && <> Valable jusqu’au {formatDate(created.expiresAt)}.</>}
      </FreshKey>

      <div className="panel">
        <Toolbar count={rows.length}>
          <SearchInput
            value={filters.search}
            onChange={(value) => setFilters((c) => ({ ...c, search: value }))}
            placeholder="Code, étiquette, détenteur…"
          />
          <FilterSelect
            label="État"
            value={filters.status}
            onChange={(value) => setFilters((c) => ({ ...c, status: value }))}
            options={STATUS_FILTERS}
          />
          <ResetFilters active={filtered} onReset={() => setFilters(EMPTY)} />
        </Toolbar>

        <DataTable
          columns={columns}
          rows={rows}
          loading={loading}
          rowClass={(key) => ('active' === key.status || 'unclaimed' === key.status ? undefined : 'row-dim')}
          empty={filtered ? 'Aucune clé ne correspond à ces filtres.' : 'Aucune clé IA émise pour l’instant.'}
        />
      </div>
    </>
  )
}
