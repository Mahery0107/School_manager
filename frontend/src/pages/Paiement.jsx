import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, ar, aujourdhui, lireErreur, MODES, nomComplet } from '../api'
import ApercuPdf from '../components/ApercuPdf'
import Recu from '../components/Recu'
import RechercheEleve from '../components/RechercheEleve'
import Situation from '../components/Situation'
import { Alerte, Champ, EnTete } from '../components/ui'

export default function Paiement() {
  const [params, setParams] = useSearchParams()
  const inscriptionId = params.get('inscription')
  const [inscription, setInscription] = useState(null)
  const [droit, setDroit] = useState(false)
  const [mois, setMois] = useState([])
  const [mode, setMode] = useState('especes')
  const [reference, setReference] = useState('')
  const [date, setDate] = useState(aujourdhui())
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [recu, setRecu] = useState(null)
  const [pdf, setPdf] = useState(null)
  const fermerPdf = useCallback(() => setPdf(null), [])

  const charger = (id) =>
    api
      .get(`/inscriptions/${id}`)
      .then((r) => setInscription(r.data))
      .catch((e) => setMessage(lireErreur(e).message))

  useEffect(() => {
    setDroit(false)
    setMois([])
    setErreurs({})
    if (inscriptionId) charger(inscriptionId)
    else setInscription(null)
  }, [inscriptionId])

  const s = inscription?.situation
  const total = s ? (droit ? s.droit_inscription : 0) + mois.length * s.ecolage_mensuel : 0

  const basculer = (m) => setMois((l) => (l.includes(m) ? l.filter((x) => x !== m) : [...l, m].sort()))

  /** Sélectionne les N prochains mois impayés, dans l'ordre. */
  const prochainsMois = (n) => setMois(s.mois.filter((x) => !x.paye).slice(0, n).map((x) => x.mois))

  const soumettre = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    setMessage('')
    try {
      const { data } = await api.post('/paiements', {
        inscription_id: inscription.id,
        droit,
        mois,
        mode,
        reference: mode === 'especes' ? null : reference,
        date_paiement: date,
      })
      setRecu(data)
      setInscription({ ...inscription, situation: data.situation })
      setDroit(false)
      setMois([])
      setReference('')
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreurs(champs)
      setMessage(message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <EnTete titre="Encaisser un paiement" sousTitre="Droit d'inscription et écolages mensuels." />
      <Alerte onFermer={() => setMessage('')}>{message}</Alerte>

      {!inscription && (
        <div className="carte">
          <h3 className="carte-titre">Rechercher un élève inscrit cette année</h3>
          <RechercheEleve
            source="inscriptions"
            onChoisir={(i) => setParams({ inscription: i.id })}
            rendreDetail={(i) => i.classe.nom}
          />
        </div>
      )}

      {inscription && (
        <div className="paiement-layout">
          <div className="carte">
            <div className="fiche-resume ligne">
              <div>
                <strong>{nomComplet(inscription.etudiant)}</strong>{' '}
                <span className="mono muted">{inscription.etudiant.matricule}</span>
                <div className="muted">
                  {inscription.classe.nom} · {inscription.annee_scolaire.libelle}
                </div>
              </div>
              <button className="btn btn-ghost" onClick={() => setParams({})}>
                Changer d'élève
              </button>
            </div>

            <h3 className="carte-titre">Cliquez sur les éléments à payer</h3>
            <Situation
              situation={s}
              selection={mois}
              onBasculer={basculer}
              droitSelectionne={droit}
              onBasculerDroit={() => setDroit(!droit)}
            />
            <div className="raccourcis">
              <span className="muted">Raccourcis :</span>
              <button type="button" className="btn btn-petit" onClick={() => prochainsMois(1)}>
                Prochain mois
              </button>
              <button type="button" className="btn btn-petit" onClick={() => prochainsMois(3)}>
                3 mois
              </button>
              <button type="button" className="btn btn-petit" onClick={() => prochainsMois(100)}>
                Tout le reste
              </button>
              {(mois.length > 0 || droit) && (
                <button
                  type="button"
                  className="btn btn-petit btn-ghost"
                  onClick={() => {
                    setMois([])
                    setDroit(false)
                  }}
                >
                  Effacer
                </button>
              )}
            </div>
            {(erreurs.mois || erreurs.droit) && <p className="champ-erreur">{erreurs.mois || erreurs.droit}</p>}
          </div>

          <form className="carte caisse" onSubmit={soumettre}>
            <h3 className="carte-titre">Règlement</h3>
            <ul className="caisse-lignes">
              {droit && (
                <li>
                  <span>Droit d'inscription</span>
                  <span>{ar(s.droit_inscription)}</span>
                </li>
              )}
              {mois.length > 0 && (
                <li>
                  <span>Écolage × {mois.length} mois</span>
                  <span>{ar(mois.length * s.ecolage_mensuel)}</span>
                </li>
              )}
              {!droit && mois.length === 0 && <li className="muted">Aucun élément sélectionné.</li>}
            </ul>
            <div className="caisse-total">
              <span>Total</span>
              <strong>{ar(total)}</strong>
            </div>

            <Champ label="Mode de paiement" requis erreur={erreurs.mode}>
              <select value={mode} onChange={(e) => setMode(e.target.value)}>
                {Object.entries(MODES).map(([k, v]) => (
                  <option key={k} value={k}>
                    {v}
                  </option>
                ))}
              </select>
            </Champ>
            {mode !== 'especes' && (
              <Champ label="Référence (n° transaction / chèque)" requis erreur={erreurs.reference}>
                <input value={reference} onChange={(e) => setReference(e.target.value)} />
              </Champ>
            )}
            <Champ label="Date du paiement" requis erreur={erreurs.date_paiement}>
              <input type="date" value={date} max={aujourdhui()} onChange={(e) => setDate(e.target.value)} />
            </Champ>

            <button className="btn btn-primaire btn-bloc" disabled={envoi || total === 0}>
              {envoi ? 'Enregistrement…' : `Encaisser ${ar(total)}`}
            </button>
          </form>
        </div>
      )}

      {recu && (
        <div className="modale-fond" onClick={() => setRecu(null)}>
          <div className="modale" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Reçu de paiement">
            <Recu recu={recu} />
            <div className="form-actions no-print">
              <button className="btn btn-ghost" onClick={() => setRecu(null)}>
                Fermer
              </button>
              <button className="btn btn-primaire" onClick={() => setPdf(recu.numero_recu)}>
                Imprimer le reçu
              </button>
            </div>
          </div>
        </div>
      )}

      {pdf && <ApercuPdf numero={pdf} onFermer={fermerPdf} />}
    </>
  )
}
