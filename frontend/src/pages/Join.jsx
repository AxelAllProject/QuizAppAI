import { useSearchParams } from 'react-router-dom'
import JoinForm from '../components/JoinForm'
import { Icon } from '../components/Icon'

/** Page pour rejoindre une partie en direct avec son code PIN. */
export default function Join() {
  const [params] = useSearchParams()

  return (
    <div className="page play-shell">
      <JoinForm
        initialPin={params.get('pin') ?? ''}
        className="card stack"
        style={{ maxWidth: 440, margin: '2rem auto', textAlign: 'center' }}
      >
        <span className="eyebrow" style={{ justifyContent: 'center' }}>
          <Icon name="gamepad" size={14} /> Partie en classe
        </span>
        <h1>Rejoindre une partie</h1>
        <p className="muted">
          Saisis le code PIN affiché sur l’écran de ton professeur : tu joueras en même temps que toute la classe.
        </p>
      </JoinForm>
    </div>
  )
}
