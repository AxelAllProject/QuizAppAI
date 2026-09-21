import { useId, useState } from 'react'

/**
 * Courbe d'évolution d'une seule mesure, en SVG (aucune bibliothèque de graphiques).
 *
 * Une mesure par graphique, volontairement : superposer les pages vues et les comptes
 * actifs demanderait deux échelles verticales sur le même cadre, ce qui laisse lire
 * n'importe quel rapport entre les deux courbes selon la façon dont on les cadre.
 * Deux graphiques côte à côte disent la même chose sans mentir.
 *
 * Pas de légende : il n'y a qu'une série, et le titre la nomme déjà.
 */
export default function Sparkline({ points, label, formatValue = (value) => value, height = 120 }) {
  const clipId = useId()
  const [hovered, setHovered] = useState(null)

  if (points.length === 0) return <p className="table-empty">Aucune donnée sur cette période.</p>

  const width = 600
  const top = 8
  const bottom = height - 8
  // L'échelle part toujours de zéro : commencer au minimum transformerait une variation
  // de 2 % en falaise. Le « 1 » de repli évite une division par zéro quand tout est plat.
  const max = Math.max(1, ...points.map((point) => point.value))
  const step = points.length > 1 ? width / (points.length - 1) : 0

  const x = (index) => (points.length > 1 ? index * step : width / 2)
  const y = (value) => bottom - (value / max) * (bottom - top)

  const line = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(point.value)}`).join(' ')
  const area = `${line} L ${x(points.length - 1)} ${bottom} L ${x(0)} ${bottom} Z`

  /** Point le plus proche du curseur : la cible est bien plus large que le marqueur. */
  function track(event) {
    const box = event.currentTarget.getBoundingClientRect()
    const ratio = (event.clientX - box.left) / box.width
    setHovered(Math.max(0, Math.min(points.length - 1, Math.round(ratio * (points.length - 1)))))
  }

  const active = hovered === null ? null : points[hovered]

  return (
    <div className="sparkline">
      <svg
        viewBox={`0 0 ${width} ${height}`}
        preserveAspectRatio="none"
        role="img"
        aria-label={`${label} : ${points.length} jours, maximum ${formatValue(max)}`}
        onMouseMove={track}
        onMouseLeave={() => setHovered(null)}
      >
        <defs>
          <linearGradient id={clipId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--accent)" stopOpacity="0.22" />
            <stop offset="100%" stopColor="var(--accent)" stopOpacity="0" />
          </linearGradient>
        </defs>

        <path d={area} fill={`url(#${clipId})`} />
        {/* vectorEffect : le trait garde 2 px quels que soient l'étirement et la largeur réelle. */}
        <path
          d={line}
          fill="none"
          stroke="var(--accent)"
          strokeWidth="2"
          strokeLinejoin="round"
          strokeLinecap="round"
          vectorEffect="non-scaling-stroke"
        />

        {active && (
          <>
            <line x1={x(hovered)} y1={top} x2={x(hovered)} y2={bottom} stroke="var(--border-strong)" strokeWidth="1" vectorEffect="non-scaling-stroke" />
            {/* Anneau de la couleur du fond : le marqueur reste lisible même posé sur la courbe. */}
            <circle cx={x(hovered)} cy={y(active.value)} r="5" fill="var(--accent)" stroke="var(--surface)" strokeWidth="2" vectorEffect="non-scaling-stroke" />
          </>
        )}
      </svg>

      <p className="sparkline-caption">
        {active ? (
          <>
            <b>{formatValue(active.value)}</b> — {active.label}
          </>
        ) : (
          <>
            Maximum <b>{formatValue(max)}</b> · survole la courbe pour le détail d’un jour
          </>
        )}
      </p>
    </div>
  )
}
