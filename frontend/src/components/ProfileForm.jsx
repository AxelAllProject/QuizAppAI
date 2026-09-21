import { useState } from 'react'
import { api } from '../api'
import { useAuth } from '../auth'
import { ErrorBox, Field } from './ui'

/**
 * Modification de l'e-mail, du pseudo et du mot de passe depuis « Mon compte ».
 *
 * Seuls les champs réellement changés partent au serveur : renvoyer son propre e-mail
 * déclencherait une vérification d'unicité inutile, et renvoyer un mot de passe vide
 * le remplacerait par rien. Le mot de passe actuel, lui, est exigé dans tous les cas —
 * c'est ce qui empêche un jeton volé de détourner l'adresse de connexion du compte.
 */
export default function ProfileForm({ onDone }) {
  const { user, updateUser } = useAuth()
  const [form, setForm] = useState({ name: user.name, email: user.email, newPassword: '', password: '' })
  const [state, setState] = useState({ busy: false, error: null })

  const set = (field) => (event) => setForm({ ...form, [field]: event.target.value })

  const changes = {
    ...(form.name.trim() !== user.name && { name: form.name.trim() }),
    ...(form.email.trim() !== user.email && { email: form.email.trim() }),
    ...(form.newPassword && { newPassword: form.newPassword }),
  }

  const tooShort = form.newPassword.length > 0 && form.newPassword.length < 8
  const ready = Object.keys(changes).length > 0 && form.password && !tooShort

  /** Envoie les seuls champs modifiés, puis rafraîchit le compte affiché partout. */
  async function submit(event) {
    event.preventDefault()
    setState({ busy: true, error: null })

    try {
      updateUser(await api('/api/me', { method: 'PUT', body: { ...changes, password: form.password } }))
      onDone(true)
    } catch (err) {
      setState({ busy: false, error: err })
    }
  }

  return (
    <form className="stack" onSubmit={submit} style={{ marginTop: '1rem' }}>
      <ErrorBox error={state.error} />

      <Field label="Pseudo" hint="Visible par les autres joueurs dans les classements.">
        <input value={form.name} onChange={set('name')} autoComplete="nickname" maxLength={40} />
      </Field>

      <Field label="Adresse e-mail" hint="C’est elle qui sert à te connecter.">
        <input type="email" value={form.email} onChange={set('email')} autoComplete="email" maxLength={180} />
      </Field>

      <Field
        label="Nouveau mot de passe"
        hint={tooShort ? 'Au moins 8 caractères.' : 'À laisser vide pour garder le mot de passe actuel.'}
      >
        <input type="password" value={form.newPassword} onChange={set('newPassword')} autoComplete="new-password" />
      </Field>

      <Field label="Confirme avec ton mot de passe actuel">
        <input type="password" value={form.password} onChange={set('password')} autoComplete="current-password" />
      </Field>

      <div className="row">
        <button className="btn primary" type="submit" disabled={state.busy || !ready}>
          {state.busy ? 'Enregistrement…' : 'Enregistrer'}
        </button>
        <button className="btn ghost" type="button" onClick={() => onDone(false)}>
          Annuler
        </button>
      </div>
    </form>
  )
}
