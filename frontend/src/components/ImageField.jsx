import { useRef, useState } from 'react'
import { assetUrl, uploadImage } from '../api'
import { ErrorBox } from './ui'

/** Téléverse une image dès qu'elle est choisie ; le formulaire ne garde que son chemin. */
export default function ImageField({ value, onChange, label = 'Ajouter une image' }) {
  const input = useRef(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  /** Téléverse l'image choisie et renvoie son chemin au formulaire. */
  async function pick(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    setBusy(true)
    setError(null)
    try {
      onChange(await uploadImage(file))
    } catch (err) {
      setError(err)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="image-field">
      {value ? (
        <div className="image-preview">
          <img src={assetUrl(value)} alt="" />
          <div className="row">
            <button type="button" className="btn ghost sm" onClick={() => input.current.click()} disabled={busy}>
              {busy ? 'Envoi…' : 'Remplacer'}
            </button>
            <button type="button" className="btn danger sm" onClick={() => onChange(null)}>
              Retirer
            </button>
          </div>
        </div>
      ) : (
        <button type="button" className="image-drop" onClick={() => input.current.click()} disabled={busy}>
          {busy ? 'Envoi…' : `+ ${label}`}
          <small>JPEG, PNG, GIF ou WebP — 5 Mo maximum</small>
        </button>
      )}

      <input
        ref={input}
        type="file"
        accept="image/jpeg,image/png,image/gif,image/webp"
        hidden
        onChange={pick}
      />
      <ErrorBox error={error} />
    </div>
  )
}
