import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import App from './App'
import { AuthProvider, useAuth } from './auth'
import Account from './pages/Account'
import AccessKeysPage from './pages/admin/AccessKeysPage'
import Accounts from './pages/admin/Accounts'
import AdminLayout from './pages/admin/AdminLayout'
import AiKeysPage from './pages/admin/AiKeysPage'
import Games from './pages/admin/Games'
import Overview from './pages/admin/Overview'
import Community from './pages/Community'
import Editor from './pages/Editor'
import History from './pages/History'
import Join from './pages/Join'
import Library from './pages/Library'
import LiveHost from './pages/LiveHost'
import LivePlayer from './pages/LivePlayer'
import Login from './pages/Login'
import Play from './pages/Play'
import Privacy from './pages/Privacy'
import Result from './pages/Result'
import Scores from './pages/Scores'
import './styles.css'

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
