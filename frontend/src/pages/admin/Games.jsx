import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../api'
import { DataTable, ResetFilters, SearchInput, SectionHead, Toolbar } from '../../components/admin'
import { ErrorBox, formatDate } from '../../components/ui'

/**
 * Historique des parties et classement. L'API des sessions ne filtre pas côté
 * serveur : la liste est bornée aux 50 dernières parties, donc la recherche se fait ici.
 */
export default function Games() {
  const [sessions, setSessions] = useState([])
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [search, setSearch] = useState('')

  useEffect(() => {
    Promise.all([api('/api/sessions?all=1'), api('/api/stats')])
      .then(([sessions, stats]) => {
        setSessions(sessions)
        setStats(stats)
      })
      .catch(setError)
      .finally(() => setLoading(false))
  }, [])

  const term = search.trim().toLowerCase()

  const rows = useMemo(
    () =>
      term
        ? sessions.filter(
            (session) =>
              session.player.toLowerCase().includes(term) || session.quizTitle.toLowerCase().includes(term),
          )
        : sessions,
    [sessions, term],
  )

  const columns = [
    { key: 'player', header: 'Joueur', cell: (session) => <b>{session.player}</b> },
    { key: 'quiz', header: 'Quiz', cell: (session) => session.quizTitle },
    {
      key: 'score',
      header: 'Score',
      align: 'right',
      cell: (session) => `${session.score} / ${session.total}`,
    },
    { key: 'playedAt', header: 'Jouée le', cell: (session) => <span className="muted">{formatDate(session.playedAt)}</span> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      cell: (session) => (
        <Link className="btn ghost sm" to={`/session/${session.id}`}>
          Détail
        </Link>
      ),
    },
  ]

  return (
    <>
      <SectionHead title="Parties">
        Les 50 dernières parties jouées sur la plateforme, et le classement général calculé sur toutes les parties. Chaque ligne ouvre
        la correction détaillée.
      </SectionHead>

      <ErrorBox error={error} />

      <div className="panel" style={{ marginBottom: '1.25rem' }}>
        <header className="panel-head">
          <h2>Classement</h2>
          {stats && <span className="muted">{stats.globalAccuracy}% de réussite globale</span>}
        </header>
        {stats?.leaderboard?.length ? (
          <ol className="rank-list">
            {stats.leaderboard.map((row, position) => (
              <li key={row.player}>
                <span className="rank-position">{position + 1}</span>
                <span className="rank-name">{row.player}</span>
                <span className="muted">
                  {row.games} partie{row.games > 1 ? 's' : ''} · {row.score} / {row.total}
                </span>
                <span className="status status-ok">{row.accuracy}%</span>
              </li>
            ))}
          </ol>
        ) : (
          <p className="table-empty">Aucune partie enregistrée pour l’instant.</p>
        )}
      </div>

      <div className="panel">
        <Toolbar count={rows.length} total={sessions.length}>
          <SearchInput value={search} onChange={setSearch} placeholder="Joueur ou quiz…" />
          <ResetFilters active={search !== ''} onReset={() => setSearch('')} />
        </Toolbar>

        <DataTable
          columns={columns}
          rows={rows}
          loading={loading}
          empty={term ? 'Aucune partie ne correspond à cette recherche.' : 'Aucune partie enregistrée pour l’instant.'}
        />
      </div>
    </>
  )
}
