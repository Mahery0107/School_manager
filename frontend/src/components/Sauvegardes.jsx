import { useEffect, useState } from 'react'
import { api, lireErreur } from '../api'
import { Alerte, Badge } from './ui'

const taille = (o) => (o > 1024 * 1024 ? `${(o / 1024 / 1024).toFixed(1)} Mo` : `${Math.max(1, Math.round(o / 1024))} Ko`)
const dateHeure = (s) => `${s.slice(8, 10)}/${s.slice(5, 7)}/${s.slice(0, 4)} à ${s.slice(11, 16)}`

/** Paramètres → Sauvegardes : sauvegarde automatique quotidienne, sauvegarde immédiate et téléchargement. */
export default function Sauvegardes() {
  const [donnees, setDonnees] = useState(null)
  const [message, setMessage] = useState({ type: 'erreur', texte: '' })
  const [envoi, setEnvoi] = useState(false)

  const charger = () =>
    api
      .get('/sauvegardes')
      .then((r) => setDonnees(r.data))
      .catch((e) => setMessage({ type: 'erreur', texte: lireErreur(e).message }))

  useEffect(() => {
    charger()
  }, [])

  const sauvegarderMaintenant = async () => {
    setEnvoi(true)
    try {
      const r = await api.post('/sauvegardes')
      setMessage({ type: 'succes', texte: r.data.message })
      charger()
    } catch (e) {
      setMessage({ type: 'erreur', texte: lireErreur(e).message })
    } finally {
      setEnvoi(false)
    }
  }

  const telecharger = async (fichier) => {
    try {
      const r = await api.get(`/sauvegardes/${encodeURIComponent(fichier)}`, { responseType: 'blob' })
      const url = URL.createObjectURL(r.data)
      const a = document.createElement('a')
      a.href = url
      a.download = fichier
      a.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch (e) {
      setMessage({ type: 'erreur', texte: lireErreur(e).message })
    }
  }

  const liste = donnees?.sauvegardes || []

  return (
    <section className="carte">
      <div className="carte-entete">
        <h3 className="carte-titre">Sauvegardes de la base de données</h3>
        <button className="btn btn-primaire btn-petit" onClick={sauvegarderMaintenant} disabled={envoi}>
          {envoi ? 'Sauvegarde…' : 'Sauvegarder maintenant'}
        </button>
      </div>
      <p className="muted petit">
        Une sauvegarde automatique est faite chaque soir et conservée {donnees?.conserver_jours ?? 30} jours.
        Téléchargez-en une régulièrement (par exemple chaque semaine) et gardez-la hors du serveur : clé USB, Google
        Drive…
      </p>
      <Alerte type={message.type} onFermer={() => setMessage({ ...message, texte: '' })}>
        {message.texte}
      </Alerte>

      {liste.length === 0 ? (
        <p className="muted">Aucune sauvegarde pour le moment.</p>
      ) : (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th className="num">Taille</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {liste.slice(0, 15).map((s) => (
                <tr key={s.fichier}>
                  <td title={s.fichier}>{dateHeure(s.date)}</td>
                  <td>{s.automatique ? <Badge ton="info">Automatique</Badge> : <Badge>Manuelle</Badge>}</td>
                  <td className="num">{taille(s.taille)}</td>
                  <td className="num">
                    <button className="btn btn-petit" onClick={() => telecharger(s.fichier)}>
                      Télécharger
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
