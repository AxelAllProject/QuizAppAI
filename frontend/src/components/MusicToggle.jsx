import { useEffect, useState } from 'react'
import { startMusic, stopMusic } from '../music'
import { Icon } from './Icon'

const STORAGE_KEY = 'quizlab.music'

function readPreference() {
  try {
    return localStorage.getItem(STORAGE_KEY) !== 'off'
  } catch {
    return true
  }
}

/** Joue la musique tant que `playing` est vrai ; le bouton la coupe, et le choix est retenu. */
export default function MusicToggle({ playing }) {
  const [enabled, setEnabled] = useState(readPreference)

  useEffect(() => {
    if (playing && enabled) startMusic()
    else stopMusic()
  }, [playing, enabled])

  useEffect(() => stopMusic, [])

  function toggle() {
    const next = !enabled
    setEnabled(next)
    try {
      localStorage.setItem(STORAGE_KEY, next ? 'on' : 'off')
    } catch {
      // Stockage indisponible (navigation privée) : le choix vaut pour cette page seulement.
    }
  }

  return (
    <button
      type="button"
      className="btn ghost sm"
      onClick={toggle}
      aria-pressed={enabled}
      title={enabled ? 'Couper la musique' : 'Activer la musique'}
    >
      <Icon name={enabled ? 'volume' : 'volume-x'} size={16} /> Musique
    </button>
  )
}
