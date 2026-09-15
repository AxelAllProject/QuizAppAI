/**
 * Jeu d'icônes de l'interface : SVG au trait sur une grille 24×24, qui héritent
 * de la couleur du texte (currentColor). Remplace les emojis, dont le rendu
 * change d'un système à l'autre.
 */
const dot = (cx, cy, r = 1) => <circle cx={cx} cy={cy} r={r} fill="currentColor" stroke="none" />

const ICONS = {
  // Interface
  x: <path d="M18 6 6 18M6 6l12 12" />,
  check: <path d="M20 6 9 17l-5-5" />,
  search: (
    <>
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </>
  ),
  'check-circle': (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="m8.5 12 2.5 2.5 5-5" />
    </>
  ),
  volume: <path d="M11 5 6 9H3v6h3l5 4zM15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14" />,
  'volume-x': <path d="M11 5 6 9H3v6h3l5 4zM16 9l6 6M22 9l-6 6" />,
  sparkles: (
    <path d="M11 3l1.8 5.2L18 10l-5.2 1.8L11 17l-1.8-5.2L4 10l5.2-1.8zM19 14l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" />
  ),
  refresh: <path d="M20 11A8 8 0 0 0 5.7 6.1L4 8M4 3v5h5M4 13a8 8 0 0 0 14.3 4.9L20 16M20 21v-5h-5" />,
  list: (
    <>
      <path d="M9 6h12M9 12h12M9 18h12" />
      {dot(4, 6)}
      {dot(4, 12)}
      {dot(4, 18)}
    </>
  ),
  dashboard: (
    <>
      <rect x="3" y="3" width="8" height="9" rx="1.5" />
      <rect x="13" y="3" width="8" height="5" rx="1.5" />
      <rect x="13" y="12" width="8" height="9" rx="1.5" />
      <rect x="3" y="16" width="8" height="5" rx="1.5" />
    </>
  ),
  key: (
    <>
      <circle cx="8" cy="15" r="4.5" />
      <path d="m11.2 11.8 8.8-8.8M16.5 6.5l3 3M14 9l2 2" />
    </>
  ),
  clipboard: (
    <>
      <rect x="8.5" y="3" width="7" height="4" rx="1" />
      <path d="M15.5 5H17a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1.5M9 14l2 2 4-4" />
    </>
  ),

  // Classe et progression
  trophy: <path d="M8 21h8M12 16v5M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3" />,
  crown: <path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5z" />,
  award: (
    <>
      <circle cx="12" cy="9" r="6" />
      <path d="m8.5 13.8-1.5 7.2 5-3 5 3-1.5-7.2" />
    </>
  ),
  users: (
    <>
      <circle cx="9" cy="8" r="3.5" />
      <path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.3a3.5 3.5 0 0 1 0 7.4M18 14.2a6.5 6.5 0 0 1 3.5 5.8" />
    </>
  ),
  target: (
    <>
      <circle cx="12" cy="12" r="9" />
      <circle cx="12" cy="12" r="5" />
      {dot(12, 12, 1.5)}
    </>
  ),
  layers: <path d="m12 3 9 5-9 5-9-5zM3 12.5l9 5 9-5M3 17l9 5 9-5" />,
  chart: <path d="M4 20h16M7 16v-5M12 16V6M17 16V9" />,
  compass: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="m15.5 8.5-2 5-5 2 2-5z" />
    </>
  ),
  gamepad: (
    <>
      <path d="M6.5 7h11A4.5 4.5 0 0 1 22 11.5v1a4 4 0 0 1-7.3 2.3L14 14h-4l-.7.8A4 4 0 0 1 2 12.5v-1A4.5 4.5 0 0 1 6.5 7zM7.5 9.5v4M5.5 11.5h4" />
      {dot(15.5, 11.5)}
      {dot(18, 10)}
    </>
  ),
  pen: <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4zM14.5 5.5l4 4" />,
  lightbulb: <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.4 1 1.1 1 1.9v.2h5v-.2c0-.8.4-1.5 1-1.9A6 6 0 0 0 12 3z" />,

  // Matières
  book: <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5" />,
  'book-open': <path d="M2 5h6a4 4 0 0 1 4 4v11a3 3 0 0 0-3-3H2zM22 5h-6a4 4 0 0 0-4 4v11a3 3 0 0 1 3-3h7z" />,
  calculator: (
    <>
      <rect x="5" y="2.5" width="14" height="19" rx="2" />
      <path d="M8.5 6.5h7M15.5 14v4" />
      {dot(8.5, 11)}
      {dot(12, 11)}
      {dot(15.5, 11)}
      {dot(8.5, 14.5)}
      {dot(12, 14.5)}
      {dot(8.5, 18)}
      {dot(12, 18)}
    </>
  ),
  landmark: <path d="M3 21h18M5 21v-10M9.5 21v-10M14.5 21v-10M19 21v-10M2 11h20L12 3z" />,
  map: <path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2zM9 4v14M15 6v14" />,
  flask: <path d="M9 3h6M10 3v6l-5.5 9.5A1.7 1.7 0 0 0 6 21h12a1.7 1.7 0 0 0 1.5-2.5L14 9V3M7 15h10" />,
  globe: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18" />
    </>
  ),
  palette: (
    <>
      <path d="M12 3a9 9 0 0 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2h2.3A4.7 4.7 0 0 0 21 11.7C21 6.9 17 3 12 3z" />
      {dot(7.5, 11)}
      {dot(10, 7)}
      {dot(15, 7.5)}
    </>
  ),
  ball: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="m12 7.5 4 2.9-1.5 4.6h-5L8 10.4zM12 3v4.5M16 10.4l4.3-1.4M14.5 15l2.6 3.8M9.5 15l-2.6 3.8M8 10.4 3.7 9" />
    </>
  ),
  laptop: (
    <>
      <rect x="4" y="4" width="16" height="11" rx="1.5" />
      <path d="M2 19h20M10 8l-2 1.5 2 1.5M14 8l2 1.5-2 1.5" />
    </>
  ),
}

export function Icon({ name, size = 18, strokeWidth = 2, className = '', ...rest }) {
  return (
    <svg
      className={`icon ${className}`}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      {...rest}
    >
      {ICONS[name] ?? ICONS.book}
    </svg>
  )
}

const MEDAL_COLORS = {
  1: ['#f5b921', '#b97f00'],
  2: ['#c6cdd9', '#7d8697'],
  3: ['#dc9a67', '#9c5f36'],
}

/** Médaille des trois premières places, avec le rang gravé. */
export function Medal({ rank, size = 22 }) {
  const [light, dark] = MEDAL_COLORS[rank]

  return (
    <svg className="icon medal" width={size} height={size} viewBox="0 0 24 24" role="img" aria-label={rank === 1 ? '1re place' : `${rank}e place`}>
      <path d="M6.5 1.5h4.2L13.5 9H9.3z" fill="#4a5bd4" />
      <path d="M17.5 1.5h-4.2L10.5 9h4.2z" fill="#ef6a3f" />
      <circle cx="12" cy="15" r="7.5" fill={dark} />
      <circle cx="12" cy="15" r="6" fill={light} />
      <text x="12" y="18.3" textAnchor="middle" fontSize="9" fontWeight="700" fill="#fff" fontFamily="Fredoka, sans-serif">
        {rank}
      </text>
    </svg>
  )
}
