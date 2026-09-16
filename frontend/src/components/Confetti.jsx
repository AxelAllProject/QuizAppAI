import { useEffect, useState } from 'react'

/** Couleurs des confettis, reprises du thème. */
const COLORS = ['var(--accent)', 'var(--pop)', 'var(--gold)', 'var(--success)', 'var(--accent-strong)']

/** Petite pluie de confettis en CSS pur, pour célébrer un bon score sans dépendance externe. */
export default function Confetti({ count = 70 }) {
  const [visible, setVisible] = useState(true)

  // Initialiseur paresseux plutôt que useMemo : le tirage aléatoire n'a besoin
  // d'avoir lieu qu'une fois, au montage, jamais à un rendu ultérieur.
  const [pieces] = useState(() =>
    Array.from({ length: count }, (_, i) => ({
      id: i,
      left: Math.random() * 100,
      delay: Math.random() * 0.5,
      duration: 2.4 + Math.random() * 1.6,
      rotation: Math.round(Math.random() * 360),
      drift: Math.round((Math.random() - 0.5) * 200),
      width: 6 + Math.random() * 6,
      height: 4 + Math.random() * 6,
      color: COLORS[i % COLORS.length],
    })),
  )

  useEffect(() => {
    const timer = setTimeout(() => setVisible(false), 4200)
    return () => clearTimeout(timer)
  }, [])

  if (!visible) return null

  return (
    <div className="confetti" aria-hidden="true">
      {pieces.map((piece) => (
        <span
          key={piece.id}
          className="confetti-piece"
          style={{
            left: `${piece.left}%`,
            width: `${piece.width}px`,
            height: `${piece.height}px`,
            background: piece.color,
            '--delay': `${piece.delay}s`,
            '--duration': `${piece.duration}s`,
            '--rotation': `${piece.rotation}deg`,
            '--drift': `${piece.drift}px`,
          }}
        />
      ))}
    </div>
  )
}
