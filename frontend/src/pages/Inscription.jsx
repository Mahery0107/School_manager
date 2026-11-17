import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ar, lireErreur, nomComplet } from '../api'
import ChampsEleve, { ELEVE_VIDE } from '../components/ChampsEleve'
import { Alerte, Champ, EnTete } from '../components/ui'
import { useReferentiels } from '../hooks'

const VIDE = { ...ELEVE_VIDE, classe_id: '', annee_scolaire_id: '' }

export default function Inscription() {
  const { classes, annees, anneeActive } = useReferentiels()
  const [form, setForm] = useState(VIDE)
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [resultat, setResultat] = useState(null)

  useEffect(() => {
    if (anneeActive && !form.annee_scolaire_id) setForm((f) => ({ ...f, annee_scolaire_id: anneeActive.id }))
  }, [anneeActive]) // eslint-disable-line react-hooks/exhaustive-deps

  const maj = (champ) => (e) => setForm({ ...form, [champ]: e.target.value })
  const classe = classes.find((c) => String(c.id) === String(form.classe_id))

  const soumettre = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    setMessage('')
    try {
      const { data } = await api.post('/inscriptions', form)
      setResultat(data)
      setForm({ ...VIDE, annee_scolaire_id: form.annee_scolaire_id })
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreurs(champs)
      setMessage(message)
    } finally {
      setEnvoi(false)
    }
  }

  if (resultat) {
    return (
      <>
        <EnTete titre="Inscription enregistrée" />
        <div className="carte succes-carte">
          <div className="succes-icone">✓</div>
          <h2>{nomComplet(resultat.etudiant)}</h2>
          <p>
            Matricule attribué : <strong className="mono">{resultat.etudiant.matricule}</strong>
          </p>
          <p className="muted">
            Inscrit(e) en <strong>{resultat.classe.nom}</strong> pour l'année {resultat.annee_scolaire.libelle}.
            Montant restant dû : {ar(resultat.situation.reste)}.
          </p>
          <div className="actions-centre">
            <Link className="btn btn-primaire" to={`/paiements/nouveau?inscription=${resultat.id}`}>
              Encaisser le droit d'inscription
            </Link>
            <button className="btn btn-ghost" onClick={() => setResultat(null)}>
              Nouvelle inscription
            </button>
          </div>
        </div>
      </>
    )
  }

  return (
    <>
      <EnTete titre="Inscription d'un nouvel élève" sousTitre="Création du dossier et inscription pour l'année scolaire." />
      <Alerte onFermer={() => setMessage('')}>{message}</Alerte>

      <form className="carte formulaire" onSubmit={soumettre} noValidate>
        <ChampsEleve form={form} maj={maj} erreurs={erreurs} />

        <fieldset>
          <legend>Scolarité</legend>
          <div className="grille-champs">
            <Champ label="Année scolaire" requis erreur={erreurs.annee_scolaire_id}>
              <select value={form.annee_scolaire_id} onChange={maj('annee_scolaire_id')}>
                <option value="">—</option>
                {annees.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.libelle} {a.active ? '(en cours)' : ''}
                  </option>
                ))}
              </select>
            </Champ>
            <Champ label="Classe" requis erreur={erreurs.classe_id}>
              <select value={form.classe_id} onChange={maj('classe_id')}>
                <option value="">—</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.nom}
                  </option>
                ))}
              </select>
            </Champ>
          </div>
          {classe && (
            <p className="tarifs">
              Droit d'inscription : <strong>{ar(classe.droit_inscription)}</strong> · Écolage mensuel :{' '}
              <strong>{ar(classe.ecolage_mensuel)}</strong>
            </p>
          )}
        </fieldset>

        <div className="form-actions">
          <button className="btn btn-primaire" disabled={envoi}>
            {envoi ? 'Enregistrement…' : "Enregistrer l'inscription"}
          </button>
        </div>
      </form>
    </>
  )
}
