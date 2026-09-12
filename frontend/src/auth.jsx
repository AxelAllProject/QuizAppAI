import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { SESSION_EXPIRED, api, readSession, writeSession } from './api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [session, setSession] = useState(readSession)
  const token = session?.token

  const open = useCallback((payload) => {
    writeSession(payload)
    setSession(payload)
    return payload.user
  }, [])

  /** Oublie la session côté navigateur (jeton refusé, compte supprimé). */
  const endSession = useCallback(() => {
    writeSession(null)
    setSession(null)
  }, [])

  const login = useCallback(
    async (email, password) => open(await api('/api/login', { method: 'POST', body: { email, password } })),
    [open],
  )

  const register = useCallback(
    async (form) => open(await api('/api/register', { method: 'POST', body: form })),
    [open],
  )

  /** Le jeton est détruit côté serveur, pas seulement oublié. */
  const logout = useCallback(async () => {
    await api('/api/logout', { method: 'POST' }).catch(() => {})
    endSession()
  }, [endSession])

  const updateUser = useCallback((user) => {
    setSession((current) => {
      if (!current) return current
      const next = { ...current, user }
      writeSession(next)
      return next
    })
  }, [])

  // Le rôle peut changer côté serveur (clé saisie ailleurs, droit retiré par un admin).
  useEffect(() => {
    if (!token) return
    api('/api/me').then(updateUser).catch(() => {})
  }, [token, updateUser])

  useEffect(() => {
    window.addEventListener(SESSION_EXPIRED, endSession)
    return () => window.removeEventListener(SESSION_EXPIRED, endSession)
  }, [endSession])

  const value = useMemo(() => {
    const user = session?.user ?? null
    return {
      user,
      login,
      register,
      logout,
      endSession,
      updateUser,
      role: user?.role ?? 'user',
      isAdmin: user?.role === 'admin',
      // Professeurs et administrateurs rédigent les quiz et animent les parties en direct.
      canCreate: user?.role === 'admin' || user?.role === 'prof',
    }
  }, [session, login, register, logout, endSession, updateUser])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth doit être utilisé dans <AuthProvider>')
  return context
}
