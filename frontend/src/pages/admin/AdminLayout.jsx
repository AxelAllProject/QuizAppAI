import { NavLink, Outlet } from 'react-router-dom'
import { Icon } from '../../components/Icon'

const SECTIONS = [
  { to: '/admin', end: true, icon: 'dashboard', label: 'Vue d’ensemble', hint: 'Activité de la plateforme' },
  { to: '/admin/comptes', icon: 'users', label: 'Comptes', hint: 'Annuaire et rôles' },
  { to: '/admin/cles', icon: 'key', label: 'Clés d’accès', hint: 'Professeur et administrateur' },
  { to: '/admin/cles-ia', icon: 'sparkles', label: 'Clés IA', hint: 'Génération de quiz' },
  { to: '/admin/parties', icon: 'list', label: 'Parties', hint: 'Historique et classement' },
]

/**
 * Coquille du back-office : une colonne de navigation, une zone de travail.
 * Chaque section est une route à part entière, donc partageable et rechargeable
 * telle quelle — on ne perd pas sa place en actualisant la page.
 */
export default function AdminLayout() {
  return (
    <div className="admin-shell">
      <aside className="admin-nav">
        <p className="admin-nav-title">Administration</p>
        <nav>
          {SECTIONS.map(({ to, end, icon, label, hint }) => (
            <NavLink key={to} to={to} end={end}>
              <span className="admin-nav-icon">
                <Icon name={icon} size={16} />
              </span>
              <span>
                <b>{label}</b>
                <small>{hint}</small>
              </span>
            </NavLink>
          ))}
        </nav>
      </aside>

      <section className="admin-main">
        <Outlet />
      </section>
    </div>
  )
}
