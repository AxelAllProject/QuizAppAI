import { StrictMode, lazy } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import App from './App'
import { AuthProvider, useAuth } from './auth'
import Account from './pages/Account'
import Community from './pages/Community'
import History from './pages/History'
import Join from './pages/Join'
import Library from './pages/Library'
import LivePlayer from './pages/LivePlayer'
import Login from './pages/Login'
import Play from './pages/Play'
import Privacy from './pages/Privacy'
import Result from './pages/Result'
import Scores from './pages/Scores'
// Polices servies par l'application elle-même (sous-ensemble latin) : aucune requête vers Google Fonts,
// qui recevrait sinon l'adresse IP de chaque visiteur (voir la page Confidentialité).
import '@fontsource/fredoka/latin-500.css'
import '@fontsource/fredoka/latin-600.css'
import '@fontsource/fredoka/latin-700.css'
import '@fontsource/plus-jakarta-sans/latin-400.css'
import '@fontsource/plus-jakarta-sans/latin-500.css'
import '@fontsource/plus-jakarta-sans/latin-600.css'
import '@fontsource/plus-jakarta-sans/latin-700.css'
import '@fontsource/plus-jakarta-sans/latin-800.css'
import './styles.css'

// Back-office, éditeur et écran d'animation ne servent qu'aux professeurs et admins :
// chargés à la demande, ils n'alourdissent pas la première visite d'un élève.
const AccessKeysPage = lazy(() => import('./pages/admin/AccessKeysPage'))
const Accounts = lazy(() => import('./pages/admin/Accounts'))
const AdminLayout = lazy(() => import('./pages/admin/AdminLayout'))
const AiKeysPage = lazy(() => import('./pages/admin/AiKeysPage'))
const Games = lazy(() => import('./pages/admin/Games'))
const Overview = lazy(() => import('./pages/admin/Overview'))
const Editor = lazy(() => import('./pages/Editor'))
const LiveHost = lazy(() => import('./pages/LiveHost'))

/** Le back-office n'a de sens que pour un admin. */
function AdminOnly({ children }) {
  const { isAdmin } = useAuth()
  return isAdmin ? children : <Navigate to="/" replace />
}

/** La rédaction de quiz demande une clé professeur ou administrateur. */
function WriterOnly({ children }) {
  const { canCreate } = useAuth()
  return canCreate ? children : <Navigate to="/" replace />
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/confidentialite" element={<Privacy />} />
          <Route element={<App />}>
            <Route path="/" element={<Library />} />
            <Route
              path="/create"
              element={
                <WriterOnly>
                  <Editor />
                </WriterOnly>
              }
            />
            <Route path="/quiz/:id" element={<Play />} />
            <Route
              path="/quiz/:id/edit"
              element={
                <WriterOnly>
                  <Editor />
                </WriterOnly>
              }
            />
            <Route path="/quiz/:id/scores" element={<Scores />} />
            <Route path="/session/:id" element={<Result />} />
            <Route path="/history" element={<History />} />
            <Route path="/communaute" element={<Community />} />
            <Route path="/join" element={<Join />} />
            <Route path="/live/:pin" element={<LivePlayer />} />
            <Route path="/live/:pin/host" element={<LiveHost />} />
            <Route path="/account" element={<Account />} />
            <Route
              path="/admin"
              element={
                <AdminOnly>
                  <AdminLayout />
                </AdminOnly>
              }
            >
              <Route index element={<Overview />} />
              <Route path="comptes" element={<Accounts />} />
              <Route path="cles" element={<AccessKeysPage />} />
              <Route path="cles-ia" element={<AiKeysPage />} />
              <Route path="parties" element={<Games />} />
            </Route>
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  </StrictMode>,
)
