import { useState } from 'react'
import { api, lireErreur, session } from '../api'
import BoutonTheme from '../components/BoutonTheme'
import { Alerte, Champ } from '../components/ui'

/** Écran de connexion du compte administrateur. */
export default function Connexion({ ecole, onConnecte, message, theme, onBasculerTheme }) {
  const [form, setForm] = useState({ identifiant: '', password: '' })
  const [erreur, setErreur] = useState(message || '')
  const [envoi, setEnvoi] = useState(false)

  const connecter = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur('')
    try {
      const r = await api.post('/login', form)
      session.ouvrir(r.data.token)
      onConnecte(r.data.user)
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreur(
        err?.response?.status === 429
          ? 'Trop de tentatives. Patientez une minute avant de réessayer.'
          : champs.identifiant || champs.password || message,
      )
      setForm((f) => ({ ...f, password: '' }))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="connexion">
      <BoutonTheme theme={theme} onBasculer={onBasculerTheme} className="btn-theme btn-theme-coin" />
      <form className="connexion-carte" onSubmit={connecter}>
        <div className="connexion-entete">
          <img className="logo-image" src="/logo.svg?v=3" alt="Logo de l'école" />
          <div>
            <h1>{ecole || 'Gestion École'}</h1>
            <p className="muted">Scolarité & caisse — accès administrateur</p>
          </div>
        </div>

        <Alerte type={message && erreur === message ? 'info' : 'erreur'}>{erreur}</Alerte>

        <Champ label="Identifiant">
          <input
            autoFocus
            autoComplete="username"
            value={form.identifiant}
            onChange={(e) => setForm({ ...form, identifiant: e.target.value })}
            required
          />
        </Champ>
        <Champ label="Mot de passe">
          <input
            type="password"
            autoComplete="current-password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required
          />
        </Champ>

        <button className="btn btn-primaire btn-bloc" disabled={envoi}>
          {envoi ? 'Connexion…' : 'Se connecter'}
        </button>
      </form>
    </div>
  )
}
