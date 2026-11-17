import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ar, dateFr, lireErreur, nomComplet } from '../api'
import { resumeParents } from '../components/ChampsEleve'
import RechercheEleve from '../components/RechercheEleve'
import { Alerte, Champ, EnTete } from '../components/ui'
import { useReferentiels } from '../hooks'

export default function Reinscription() {
  const { classes, annees, anneeActive } = useReferentiels()
  const [eleve, setEleve] = useState(null)
  const [anneeId, setAnneeId] = useState('')
  const [classeId, setClasseId] = useState('')
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [resultat, setResultat] = useState(null)

  useEffect(() => {
    if (anneeActive && !anneeId) setAnneeId(anneeActive.id)
  }, [anneeActive]) // eslint-disable-line react-hooks/exhaustive-deps

  const derniere = eleve?.derniere_inscription
  const dejaInscrit = derniere && String(derniere.annee_scolaire_id) === String(anneeId)
  // La dernière classe de la grille (3ème) est la classe finale de l'établissement
  const ordreFinal = classes.length ? Math.max(...classes.map((c) => c.ordre)) : null
  const aTermineCycle = derniere && !dejaInscrit && derniere.classe.ordre === ordreFinal

  const choisir = (e) => {
    setEleve(e)
    setErreurs({})
    setMessage('')
    // Propose par défaut la classe supérieure à la dernière classe fréquentée
    const precedente = e.derniere_inscription?.classe
    const suivante = precedente && classes.find((c) => c.ordre === precedente.ordre + 1)
    setClasseId(String((suivante || precedente)?.id || ''))
  }

  const soumettre = async (ev) => {
    ev.preventDefault()
    setEnvoi(true)
    setErreurs({})
    setMessage('')
    try {
      const { data } = await api.post('/reinscriptions', {
        etudiant_id: eleve.id,
        classe_id: classeId,
        annee_scolaire_id: anneeId,
      })
      setResultat(data)
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreurs(champs)
      setMessage(champs.etudiant_id || message)
    } finally {
      setEnvoi(false)
    }
  }

  const recommencer = () => {
    setResultat(null)
    setEleve(null)
  }

  if (resultat) {
    return (
      <>
        <EnTete titre="Réinscription enregistrée" />
        <div className="carte succes-carte">
          <div className="succes-icone">✓</div>
          <h2>{nomComplet(resultat.etudiant)}</h2>
          <p>
            Réinscrit(e) en <strong>{resultat.classe.nom}</strong> pour l'année {resultat.annee_scolaire.libelle}.
          </p>
          <p className="muted">Montant dû pour l'année : {ar(resultat.situation.total_du)}.</p>
          <div className="actions-centre">
            <Link className="btn btn-primaire" to={`/paiements/nouveau?inscription=${resultat.id}`}>
              Encaisser un paiement
            </Link>
            <button className="btn btn-ghost" onClick={recommencer}>
              Autre réinscription
            </button>
          </div>
        </div>
      </>
    )
  }

  return (
    <>
      <EnTete titre="Réinscription d'un ancien élève" sousTitre="Recherchez l'élève puis choisissez sa classe pour la nouvelle année." />
      <Alerte onFermer={() => setMessage('')}>{message}</Alerte>

      <div className="carte">
        <h3 className="carte-titre">1. Rechercher l'élève</h3>
        <RechercheEleve
          onChoisir={choisir}
          rendreDetail={(e) =>
            e.derniere_inscription
              ? `${e.derniere_inscription.classe.nom} · ${e.derniere_inscription.annee_scolaire.libelle}`
              : 'Jamais inscrit'
          }
        />
      </div>

      {eleve && (
        <form className="carte" onSubmit={soumettre}>
          <h3 className="carte-titre">2. Nouvelle inscription</h3>
          <div className="fiche-resume">
            <div>
              <strong>{nomComplet(eleve)}</strong> <span className="mono muted">{eleve.matricule}</span>
            </div>
            <div className="muted">
              Né(e) le {dateFr(eleve.date_naissance)} · {resumeParents(eleve)}
            </div>
            <div className="muted">
              Dernière inscription :{' '}
              {derniere ? (
                <strong>
                  {derniere.classe.nom} en {derniere.annee_scolaire.libelle}
                </strong>
              ) : (
                'aucune'
              )}
            </div>
          </div>

          {dejaInscrit && <Alerte type="info">Cet élève est déjà inscrit pour l'année sélectionnée.</Alerte>}
          {aTermineCycle && (
            <Alerte type="info">
              Cet élève était en {derniere.classe.nom}, classe finale de l'établissement : il ne peut être réinscrit
              qu'en redoublement.
            </Alerte>
          )}
          {!derniere && (
            <Alerte type="info">
              Cet élève n'a jamais été inscrit. Utilisez plutôt <Link to="/inscription">l'inscription d'un nouvel élève</Link>.
            </Alerte>
          )}

          <div className="grille-champs">
            <Champ label="Année scolaire" requis erreur={erreurs.annee_scolaire_id}>
              <select value={anneeId} onChange={(e) => setAnneeId(e.target.value)}>
                {annees.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.libelle} {a.active ? '(en cours)' : ''}
                  </option>
                ))}
              </select>
            </Champ>
            <Champ label="Nouvelle classe" requis erreur={erreurs.classe_id}>
              <select value={classeId} onChange={(e) => setClasseId(e.target.value)}>
                <option value="">—</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.nom}
                    {derniere && c.id === derniere.classe_id ? ' (redoublement)' : ''}
                  </option>
                ))}
              </select>
            </Champ>
          </div>

          <div className="form-actions">
            <button type="button" className="btn btn-ghost" onClick={() => setEleve(null)}>
              Annuler
            </button>
            <button className="btn btn-primaire" disabled={envoi || !classeId || dejaInscrit || !derniere}>
              {envoi ? 'Enregistrement…' : 'Valider la réinscription'}
            </button>
          </div>
        </form>
      )}
    </>
  )
}
