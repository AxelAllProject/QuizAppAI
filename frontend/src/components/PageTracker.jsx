import { useEffect, useRef } from 'react'
import { useLocation } from 'react-router-dom'
import { trackPageView } from '../analytics'

/**
 * Compte les écrans consultés. Posé au-dessus des routes, il couvre aussi la connexion
 * et la page Confidentialité, qui se visitent avant d'avoir un compte.
 *
 * Un écran n'est déclaré qu'une fois **quitté** : c'est à ce moment seulement qu'on connaît
 * le temps passé dessus. Le dernier de la visite part au `pagehide`, en requête survivante.
 */
export default function PageTracker() {
  const { pathname } = useLocation()
  // Écran courant et heure de son ouverture. Une ref, pas un état : le suivi ne doit
  // provoquer aucun rendu, sous peine de faire clignoter toute l'application à chaque page.
  const current = useRef(null)

  useEffect(() => {
    // En développement, StrictMode rejoue les effets : sans cette garde, chaque écran
    // serait compté deux fois et les durées repartiraient de zéro.
    if (current.current?.path === pathname) return

    if (current.current) {
      trackPageView(current.current.path, current.current.at)
    }

    current.current = { path: pathname, at: performance.now() }
  }, [pathname])

  useEffect(() => {
    // « pagehide » plutôt que « beforeunload » : c'est le seul que les navigateurs mobiles
    // déclenchent de façon fiable quand on referme ou met l'onglet en arrière-plan.
    const flush = () => {
      if (current.current) {
        trackPageView(current.current.path, current.current.at, true)
        current.current = null
      }
    }

    window.addEventListener('pagehide', flush)
    return () => window.removeEventListener('pagehide', flush)
  }, [])

  return null
}
