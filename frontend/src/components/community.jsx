import { Link } from 'react-router-dom'
import { useAuth } from '../auth'
import { plural, toneOf } from '../progress'
import { Icon, Medal } from './Icon'
import { Avatar } from './ui'

/** Classement général (/api/stats) : taux de bonnes réponses, puis nombre de parties. */
export function Leaderboard({ rows, limit, offset = 0 }) {
  const { user } = useAuth()

  return (
    <ol className="leaderboard" start={offset + 1}>
      {rows.slice(offset, limit).map((row, index) => {
        const position = offset + index
        return (
          <li key={row.player} className={row.player === user.name ? 'me' : undefined}>
            <span className="pos">{position < 3 ? <Medal rank={position + 1} /> : position + 1}</span>
            <Avatar name={row.player} size="sm" />
            <span className="who">
              <b>
                {row.player}
                {row.player === user.name && <span className="you">toi</span>}
              </b>
              <small>{plural(row.games, 'partie')}</small>
              <span className={`meter thin ${toneOf(row.accuracy)}`} aria-hidden="true">
                <span style={{ width: `${row.accuracy}%` }} />
              </span>
            </span>
            <span className="acc">{row.accuracy}%</span>
          </li>
        )
      })}
    </ol>
  )
}

/** Les trois premiers de la classe, sur les marches. */
export function ClassPodium({ rows }) {
  const { user } = useAuth()
  const [first, second, third] = rows
  const steps = [
    [second, 2],
    [first, 1],
    [third, 3],
  ].filter(([row]) => row)

  return (
    <div className="class-podium">
      {steps.map(([row, rank]) => (
        <div className={`step p${rank}${row.player === user.name ? ' me' : ''}`} key={rank}>
          {rank === 1 && <Icon name="crown" size={20} className="crown" />}
          <Avatar name={row.player} size={rank === 1 ? 'lg' : ''} />
          <b className="name">{row.player}</b>
          <span className="score">
            {row.accuracy}% · {plural(row.games, 'partie')}
          </span>
          <div className="block">
            <Medal rank={rank} size={30} />
          </div>
        </div>
      ))}
    </div>
  )
}

/** Chiffres clés de la communauté : quiz, joueurs, parties et réussite moyenne. */
export function CommunityPulse({ stats }) {
  const items = [
    { icon: 'layers', tone: 'accent', value: stats?.quizCount, label: 'quiz partagés' },
    { icon: 'users', tone: 'success', value: stats?.playerCount, label: 'joueurs' },
    { icon: 'target', tone: 'pop', value: stats?.sessionCount, label: 'parties jouées' },
    {
      icon: 'check-circle',
      tone: 'gold',
      value: stats ? `${stats.globalAccuracy}%` : undefined,
      label: 'de bonnes réponses',
      meter: stats?.globalAccuracy,
    },
  ]

  return (
    <div className="community-pulse">
      {items.map(({ icon, tone, value, label, meter }) => (
        <div key={label} className={`pulse-tile ${tone}`}>
          <span className="pulse-ico">
            <Icon name={icon} size={22} />
          </span>
          <span className="pulse-body">
            <b>{value ?? '—'}</b>
            <span>{label}</span>
            {meter !== undefined && (
              <span className="meter thin" aria-hidden="true">
                <span style={{ width: `${meter}%` }} />
              </span>
            )}
          </span>
        </div>
      ))}
    </div>
  )
}

/** Colonne de l'accueil : la classe reste visible pendant qu'on choisit un quiz. */
export function CommunityAside({ stats }) {
  return (
    <aside className="home-aside">
      <section className="card aside-card">
        <h2>
          <Icon name="trophy" size={18} /> Classement général
        </h2>
        {!stats ? (
          <div className="skeleton" style={{ height: 160 }} />
        ) : stats.leaderboard.length ? (
          <Leaderboard rows={stats.leaderboard} limit={5} />
        ) : (
          <p className="muted">Personne n’a encore joué : ouvre la voie !</p>
        )}
        <Link className="btn ghost sm block" to="/communaute" style={{ marginTop: '0.6rem' }}>
          Voir la communauté
        </Link>
      </section>

      <section className="card aside-card">
        <h2>
          <Icon name="chart" size={18} /> La classe en chiffres
        </h2>
        <CommunityPulse stats={stats} />
      </section>
    </aside>
  )
}
