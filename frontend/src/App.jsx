import { Link, NavLink, Navigate, Outlet, useLocation } from 'react-router-dom'
import AiChatWidget from './components/AiChatWidget'
import Brand from './components/Brand'
import { Avatar } from './components/ui'
import { useAuth } from './auth'

const ROLE_LABELS = { user: 'élève', prof: 'professeur', admin: 'administrateur' }

/** Toute l'application est derrière la connexion. */
export default function App() {
  const { user, logout, isAdmin, canCreate, role } = useAuth()
  const location = useLocation()

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }

  return (
    <div className="app">
      <header className="topbar">
        <NavLink to="/" className="brand">
          <Brand />
        </NavLink>

        <nav className="nav">
          <NavLink to="/" end>
            Accueil
          </NavLink>
          <NavLink to="/join">Rejoindre</NavLink>
          <NavLink to="/history">Mon parcours</NavLink>
          <NavLink to="/communaute">Communauté</NavLink>
          {canCreate && <NavLink to="/create">Créer</NavLink>}
          {isAdmin && <NavLink to="/admin">Administration</NavLink>}
        </nav>

        <div className="identity">
          <Link to="/account" className="identity-link" title="Mon compte">
            <Avatar name={user.name} />
            <div style={{ lineHeight: 1.2 }}>
              <div style={{ fontSize: '0.85rem', fontWeight: 600 }}>{user.name}</div>
              <div style={{ fontSize: '0.72rem', color: canCreate ? 'var(--accent-strong)' : 'var(--text-faint)' }}>
                {ROLE_LABELS[role]}
              </div>
            </div>
          </Link>
          <button type="button" className="btn ghost sm" onClick={logout}>
            Déconnexion
          </button>
        </div>
      </header>

      <main>
        <Outlet />
      </main>

      <footer className="site-footer">
        <span>QuizLab — apprendre, jouer et progresser ensemble</span>
        <Link to="/account">Mon compte</Link>
        <Link to="/confidentialite">Confidentialité et données personnelles</Link>
      </footer>

      {canCreate && <AiChatWidget />}
    </div>
  )
}
