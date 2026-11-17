import { useState } from 'react'
import { api, lireErreur } from '../api'
import { Alerte, Champ } from './ui'

/** Paramètres → modification du compte administrateur (nom, identifiant, mot de passe). */
export default function CompteAdmin({ utilisateur, onModifie }) {
  const vide = (u) => ({
    name: u?.name || '',
    identifiant: u?.identifiant || '',
    password_actuel: '',
    password: '',
    password_confirmation: '',
  })
  const [form, setForm] = useState(() => vide(utilisateur))
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState({ type: 'erreur', texte: '' })
  const [envoi, setEnvoi] = useState(false)

  const maj = (champ) => (e) => setForm({ ...form, [champ]: e.target.value })

  const enregistrer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      const r = await api.put('/compte', form)
      setErreurs({})
      setForm(vide(r.data))
      setMessage({
        type: 'succes',
        texte: form.password ? 'Compte et mot de passe modifiés.' : 'Compte modifié.',
      })
      onModifie(r.data)
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreurs(champs)
      setMessage({ type: 'erreur', texte: message })
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <section className="carte">
      <h3 className="carte-titre">Compte administrateur</h3>
      <p className="muted petit">
        Ce compte protège l'accès à l'application. Le mot de passe actuel est demandé pour toute modification.
      </p>
      <Alerte type={message.type} onFermer={() => setMessage({ ...message, texte: '' })}>
        {message.texte}
      </Alerte>

      <form onSubmit={enregistrer}>
        <div className="grille-champs">
          <Champ label="Nom affiché" requis erreur={erreurs.name}>
            <input value={form.name} onChange={maj('name')} />
          </Champ>
          <Champ label="Identifiant de connexion" requis erreur={erreurs.identifiant}>
            <input value={form.identifiant} onChange={maj('identifiant')} autoComplete="username" />
          </Champ>
          <Champ label="Nouveau mot de passe" erreur={erreurs.password}>
            <input
              type="password"
              value={form.password}
              onChange={maj('password')}
              placeholder="Laisser vide pour ne pas changer"
              autoComplete="new-password"
            />
          </Champ>
          <Champ label="Confirmer le nouveau mot de passe">
            <input
              type="password"
              value={form.password_confirmation}
              onChange={maj('password_confirmation')}
              autoComplete="new-password"
              disabled={!form.password}
            />
          </Champ>
        </div>

        <div className="sous-formulaire">
          <Champ label="Mot de passe actuel" requis erreur={erreurs.password_actuel}>
            <input
              type="password"
              value={form.password_actuel}
              onChange={maj('password_actuel')}
              autoComplete="current-password"
            />
          </Champ>
        </div>

        <div className="form-actions">
          <button className="btn btn-primaire" disabled={envoi}>
            {envoi ? 'Enregistrement…' : 'Enregistrer le compte'}
          </button>
        </div>
      </form>
    </section>
  )
}
