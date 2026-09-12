const BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'
const STORAGE_KEY = 'quizlab.session'

/** Émis quand le serveur refuse le jeton (expiré, révoqué, compte supprimé). */
export const SESSION_EXPIRED = 'quizlab:session-expired'

try {
  // Ancienne connexion par simple pseudo : plus rien à conserver.
  localStorage.removeItem('quizlab.user')
} catch {
  // Stockage indisponible (navigation privée stricte) : rien à nettoyer.
}

/** Seul le jeton de connexion et le compte sont gardés dans le navigateur. */
export function readSession() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY)) ?? null
  } catch {
    return null
  }
}

export function writeSession(session) {
  if (session) localStorage.setItem(STORAGE_KEY, JSON.stringify(session))
  else localStorage.removeItem(STORAGE_KEY)
}

/** Les images sont servies par le backend : leur chemin enregistré est relatif à l'API. */
export function assetUrl(path) {
  return path ? `${BASE}${path}` : null
}

/**
 * Erreur d'API : `messages` contient les violations de validation renvoyées
 * par le backend, pour les afficher telles quelles dans les formulaires.
 */
export class ApiError extends Error {
  constructor(message, messages = [], status = 0) {
    super(message)
    this.messages = messages
    this.status = status
  }
}

export async function api(path, { method = 'GET', body, form } = {}) {
  const session = readSession()
  const headers = form ? {} : { 'Content-Type': 'application/json' }

  if (session?.token) {
    headers.Authorization = `Bearer ${session.token}`
  }

  let response
  try {
    response = await fetch(`${BASE}${path}`, {
      method,
      headers,
      body: form ?? (body === undefined ? undefined : JSON.stringify(body)),
    })
  } catch {
    throw new ApiError("Le serveur est injoignable. Le backend Symfony est-il démarré ?")
  }

  if (response.status === 401 && session?.token) {
    window.dispatchEvent(new Event(SESSION_EXPIRED))
  }

  if (response.status === 204) return null

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(
      payload?.error ?? (response.status === 401 ? 'Session expirée : reconnecte-toi.' : 'Une erreur est survenue.'),
      payload?.errors ?? [],
      response.status,
    )
  }

  return payload
}

/** Téléverse une image et renvoie le chemin à enregistrer dans le quiz. */
export async function uploadImage(file) {
  const form = new FormData()
  form.append('image', file)
  const { path } = await api('/api/uploads', { method: 'POST', form })
  return path
}

/** Récupère un JSON de l'API et le propose en téléchargement. */
export async function downloadJson(path, filename) {
  const data = await api(path)
  const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
