import { useState } from 'react'
import { api } from '../../api'
import { useAuth } from '../../auth'
import {
  DataTable,
  FilterSelect,
  ResetFilters,
  SearchInput,
  SectionHead,
  Toolbar,
  useFilteredList,
} from '../../components/admin'
import { Avatar, ErrorBox, formatDate } from '../../components/ui'

/** Options du filtre par rôle. */
const ROLES = [
  ['', 'Tous les rôles'],
  ['user', 'Joueur'],
  ['prof', 'Professeur'],
  ['admin', 'Administrateur'],
]

/** Filtres vides (tout afficher). */
const EMPTY = { search: '', role: '' }

/** Annuaire des comptes : on y cherche quelqu'un, et on lui change son rôle. */
export default function Accounts() {
  const { user } = useAuth()
  const [filters, setFilters] = useState(EMPTY)
  const { rows, loading, error, setError, replace } = useFilteredList('/api/users', filters)

  /** Modifie un filtre de l'annuaire. */
  function set(field, value) {
    setFilters((current) => ({ ...current, [field]: value }))
  }

  /** Change le rôle d'un compte et met à jour sa ligne. */
  async function changeRole(account, role) {
    setError(null)

    try {
      const updated = await api(`/api/users/${account.id}/role`, { method: 'PUT', body: { role } })
      // Le serveur ne recalcule pas les statistiques de jeu ici : il les renvoie à zéro.
      // On garde donc celles déjà affichées, sinon la ligne perdrait son historique.
      replace({ ...account, ...updated, games: account.games, accuracy: account.accuracy })
    } catch (err) {
      setError(err)
    }
  }

  const columns = [
    {
      key: 'name',
      header: 'Compte',
      cell: (account) => (
        <span className="cell-identity">
          <Avatar name={account.name} size="sm" />
          {account.name}
        </span>
      ),
    },
    {
      key: 'role',
      header: 'Rôle',
      cell: (account) => (
        <select
          className="inline-select"
          value={account.role}
          onChange={(e) => changeRole(account, e.target.value)}
          disabled={account.id === user.id}
          title={account.id === user.id ? 'Tu ne peux pas modifier ton propre rôle.' : undefined}
          aria-label={`Rôle de ${account.name}`}
        >
          <option value="user">joueur</option>
          <option value="prof">professeur</option>
          <option value="admin">administrateur</option>
        </select>
      ),
    },
    { key: 'games', header: 'Parties', align: 'right', cell: (account) => account.games },
    {
      key: 'accuracy',
      header: 'Réussite',
      align: 'right',
      cell: (account) => (account.accuracy === null ? <span className="muted">—</span> : `${account.accuracy}%`),
    },
    { key: 'createdAt', header: 'Inscrit le', cell: (account) => <span className="muted">{formatDate(account.createdAt)}</span> },
    {
      key: 'lastSeenAt',
      header: 'Dernière connexion',
      cell: (account) => <span className="muted">{formatDate(account.lastSeenAt)}</span>,
    },
  ]

  return (
    <>
      <SectionHead title="Comptes">
        Cherche un compte par son pseudo, filtre par rôle, et accorde ou retire les droits directement dans le
        tableau. Retirer un rôle ici est la seule façon de le reprendre : révoquer une clé n’enlève pas les droits
        déjà accordés.
      </SectionHead>

      <ErrorBox error={error} />

      <div className="panel">
        <Toolbar count={rows.length}>
          <SearchInput value={filters.search} onChange={(value) => set('search', value)} placeholder="Pseudo…" />
          <FilterSelect label="Rôle" value={filters.role} onChange={(value) => set('role', value)} options={ROLES} />
          <ResetFilters active={filters.search !== '' || filters.role !== ''} onReset={() => setFilters(EMPTY)} />
        </Toolbar>

        <DataTable
          columns={columns}
          rows={rows}
          loading={loading}
          empty={
            filters.search || filters.role
              ? 'Aucun compte ne correspond à cette recherche.'
              : 'Aucun compte pour l’instant.'
          }
        />
      </div>
    </>
  )
}
