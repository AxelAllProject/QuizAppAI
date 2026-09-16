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

/** Libellé affiché pour chaque rôle. */
const ROLE_LABELS = { prof: 'professeur', admin: 'administrateur' }

/** Options du filtre par rôle. */
const ROLE_FILTERS = [
  ['', 'Tous les rôles'],
  ['prof', 'Professeur'],
  ['admin', 'Administrateur'],
]

/** Options du filtre par état. */
const STATUS_FILTERS = [
  ['', 'Tous les états'],
  ['active', 'Active'],
  ['assigned', 'Attribuée'],
  ['expired', 'Périmée'],
  ['revoked', 'Révoquée'],
]

// Durées proposées : une formation courte, un trimestre, une année scolaire.
const DURATIONS = [
  ['', 'Sans expiration'],
  ['1', '1 jour'],
  ['7', '7 jours'],
  ['30', '30 jours'],
  ['90', '90 jours'],
  ['365', '1 an'],
]

/** Filtres vides (tout afficher). */
const EMPTY = { search: '', role: '', status: '' }

/** Émission, attribution et révocation des clés qui confèrent un rôle. */
export default function AccessKeysPage() {
  const [filters, setFilters] = useState(EMPTY)
  const { rows, loading, error, setError, replace, reload } = useFilteredList('/api/access-keys', filters)

  // La liste des comptes sert au champ d'attribution : elle ne suit pas les filtres du tableau.
  const [users, setUsers] = useState([])
  useEffect(() => {
    api('/api/users').then(setUsers).catch(setError)
  }, [setError])

  const [role, setRole] = useState('prof')
  const [label, setLabel] = useState('')
  const [expiresInDays, setExpiresInDays] = useState('')
  const [assignTo, setAssignTo] = useState(null)
  const [busy, setBusy] = useState(false)
  const [created, setCreated] = useState(null)

  /** Crée une clé d'accès (ou l'attribue à un compte) et l'affiche en tête. */
  async function create(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      const key = await api('/api/access-keys', {
        method: 'POST',
        body: {
          role,
          label: label.trim() || null,
          expiresInDays: expiresInDays ? Number(expiresInDays) : null,
          userId: assignTo,
        },
      })

      // Une clé attribuée n'a pas de code à transmettre : rien à mettre en avant.
      setCreated(assignTo ? null : key)
      setLabel('')
      setExpiresInDays('')
      setAssignTo(null)
      // Rechargement plutôt qu'un ajout local : la nouvelle clé ne satisfait pas forcément les filtres.
      reload()
    } catch (err) {
      setError(err)
    } finally {
      setBusy(false)
    }
  }

  /** Révoque une clé après confirmation. */
  async function revoke(key) {
    if (!confirm(`Révoquer la clé ${key.value} ? Elle ne donnera plus aucun rôle.`)) return

    try {
      replace(await api(`/api/access-keys/${key.id}`, { method: 'DELETE' }))
    } catch (err) {
      setError(err)
    }
  }

  const columns = [
    { key: 'value', header: 'Clé', cell: (key) => <KeyValue value={key.value} /> },
    {
      key: 'role',
      header: 'Rôle',
      cell: (key) => <span className={`badge ${key.role === 'admin' ? 'accent' : ''}`}>{ROLE_LABELS[key.role] ?? key.role}</span>,
    },
    { key: 'status', header: 'État', cell: (key) => <StatusBadge status={key.status} /> },
    {
      key: 'for',
      header: 'Étiquette / attribution',
      cell: (key) =>
        key.assignedTo ? (
          <>
            attribuée à <b>{key.assignedTo}</b>
          </>
        ) : (
          <span className="muted">{key.label ?? '—'}</span>
        ),
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
    { key: 'usageCount', header: 'Usages', align: 'right', cell: (key) => key.usageCount },
    { key: 'createdAt', header: 'Créée le', cell: (key) => <span className="muted">{formatDate(key.createdAt)}</span> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      cell: (key) =>
        'active' === key.status ? (
          <span className="row-actions">
            <CopyButton value={key.value} />
            <button type="button" className="btn danger sm" onClick={() => revoke(key)}>
              Révoquer
            </button>
          </span>
        ) : null,
    },
  ]

  const filtered = filters.search !== '' || filters.role !== '' || filters.status !== ''

  return (
    <>
      <SectionHead title="Clés d’accès">
        Une clé professeur autorise la rédaction de quiz et l’animation de parties en direct ; une clé
        administrateur donne en plus accès à ce back-office. Attribue-la à un compte pour accorder le rôle tout de
        suite, ou laisse le champ vide pour obtenir un code que la personne saisira elle-même — à l’inscription ou
        depuis « Mon compte ». Un code peut porter une date de fin : passée cette date, il ne vaut plus rien.
      </SectionHead>

      <ErrorBox error={error} />

      <form className="panel create-form" onSubmit={create}>
        <Field label="Rôle accordé">
          <select value={role} onChange={(e) => setRole(e.target.value)}>
            <option value="prof">Professeur</option>
            <option value="admin">Administrateur</option>
          </select>
        </Field>

        <Field label="Attribuer à" hint="Laisse vide pour générer un code à transmettre.">
          <UserPicker users={users} value={assignTo} onChange={setAssignTo} placeholder="Chercher un compte…" />
        </Field>

        {!assignTo && (
          <>
            <Field label="Expiration du code" hint="Au-delà, le code ne confère plus rien.">
              <select value={expiresInDays} onChange={(e) => setExpiresInDays(e.target.value)}>
                {DURATIONS.map(([days, text]) => (
                  <option key={days} value={days}>
                    {text}
                  </option>
                ))}
              </select>
            </Field>

            <Field label="Étiquette" hint="Pour se rappeler à qui le code est remis.">
              <input value={label} onChange={(e) => setLabel(e.target.value)} placeholder="Ex. Mme Martin" maxLength={120} />
            </Field>
          </>
        )}

        <button className="btn primary" type="submit" disabled={busy}>
          {busy ? 'Génération…' : assignTo ? 'Attribuer le rôle' : 'Générer une clé'}
        </button>
      </form>

      <FreshKey keyValue={created?.value} onDismiss={() => setCreated(null)}>
        Transmets-la à la personne concernée : elle la saisira à l’inscription ou dans « Mon compte ».
        {created?.expiresAt && <> Valable jusqu’au {formatDate(created.expiresAt)}.</>}
      </FreshKey>

      <div className="panel">
        <Toolbar count={rows.length}>
          <SearchInput
            value={filters.search}
            onChange={(value) => setFilters((c) => ({ ...c, search: value }))}
            placeholder="Code, étiquette, bénéficiaire…"
          />
          <FilterSelect
            label="Rôle"
            value={filters.role}
            onChange={(value) => setFilters((c) => ({ ...c, role: value }))}
            options={ROLE_FILTERS}
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
          rowClass={(key) => ('expired' === key.status || 'revoked' === key.status ? 'row-dim' : undefined)}
          empty={filtered ? 'Aucune clé ne correspond à ces filtres.' : 'Aucune clé d’accès émise pour l’instant.'}
        />
      </div>
    </>
  )
}
