import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ar, lireErreur } from '../api'
import { Alerte, EnTete } from '../components/ui'

export default function Tableau() {
  const [d, setD] = useState(null)
  const [message, setMessage] = useState('')

  useEffect(() => {
    api
      .get('/dashboard')
      .then((r) => setD(r.data))
      .catch((e) => setMessage(lireErreur(e).message))
  }, [])

  if (message) return <Alerte>{message}</Alerte>
  if (!d) return <p className="muted">Chargement…</p>
  if (!d.annee)
    return (
      <Alerte type="info">
        Aucune année scolaire active. <Link to="/parametres">Créez-en une dans les paramètres.</Link>
      </Alerte>
    )

  const pct = d.total_du ? Math.round((d.total_paye / d.total_du) * 100) : 0
  const maxClasse = Math.max(1, ...d.par_classe.map((c) => c.effectif))

  return (
    <>
      <EnTete titre="Tableau de bord" sousTitre={`Année scolaire ${d.annee.libelle}`} />

      <div className="raccourcis-grands">
        <Link to="/inscription" className="raccourci">
          <span className="raccourci-icone">＋</span>
          <span>
            <strong>Nouvelle inscription</strong>
            <small>Inscrire un nouvel élève</small>
          </span>
        </Link>
        <Link to="/reinscription" className="raccourci">
          <span className="raccourci-icone">↻</span>
          <span>
            <strong>Réinscription</strong>
            <small>Réinscrire un ancien élève</small>
          </span>
        </Link>
        <Link to="/paiements/nouveau" className="raccourci">
          <span className="raccourci-icone">Ar</span>
          <span>
            <strong>Encaisser</strong>
            <small>Droit d'inscription / écolage</small>
          </span>
        </Link>
      </div>

      <div className="stats">
        <div className="stat">
          <span className="stat-label">Effectif</span>
          <span className="stat-valeur">{d.effectif}</span>
          {d.sortis > 0 && <span className="muted petit-aide">+ {d.sortis} renvoyé(s) / parti(s)</span>}
          <span className="muted">
            {d.nouveaux} nouveaux · {d.reinscrits} réinscrits
          </span>
        </div>
        <div className="stat">
          <span className="stat-label">Encaissé (année)</span>
          <span className="stat-valeur">{ar(d.total_paye)}</span>
          <span className="muted">{pct}% du total attendu</span>
        </div>
        <div className="stat">
          <span className="stat-label">Reste à recouvrer</span>
          <span className="stat-valeur danger">{ar(d.reste)}</span>
          <span className="muted">sur {ar(d.total_du)}</span>
        </div>
        <div className="stat">
          <span className="stat-label">Élèves en retard</span>
          <span className="stat-valeur">{d.eleves_en_retard}</span>
          <span className="muted">
            {ar(d.montant_ecolage_en_retard)} d'écolage non payé après le {d.jour_limite_ecolage} du mois, ou droit impayé
          </span>
        </div>
        <div className="stat">
          <span className="stat-label">Encaissé aujourd'hui</span>
          <span className="stat-valeur">{ar(d.encaisse_aujourdhui)}</span>
        </div>
      </div>

      <div className="carte">
        <h3 className="carte-titre">Effectif par classe</h3>
        {d.par_classe.length === 0 && <p className="muted">Aucun élève inscrit pour l'instant.</p>}
        <div className="barres">
          {d.par_classe.map((c) => (
            <div className="barre" key={c.classe}>
              <span className="barre-label">{c.classe}</span>
              <div className="barre-piste">
                <div className="barre-remplie" style={{ width: `${(c.effectif / maxClasse) * 100}%` }} />
              </div>
              <span className="barre-valeur">{c.effectif}</span>
            </div>
          ))}
        </div>
      </div>
    </>
  )
}
