import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, lireErreur } from '../api'
import Recu from './Recu'
import { Alerte, Champ } from './ui'

/**
 * Fenêtre d'un reçu : impression, et annulation (erreur de saisie) avec motif obligatoire.
 * Pour corriger un paiement : annuler le reçu, puis encaisser à nouveau.
 */
export default function ModaleRecu({ recu, onFermer, onImprimer, onAnnule }) {
  const [annulation, setAnnulation] = useState(false)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const annuler = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur('')
    try {
      const r = await api.post(`/recus/${recu.numero_recu}/annuler`, { motif })
      setAnnulation(false)
      setMotif('')
      onAnnule(r.data)
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreur(champs.motif || message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="modale-fond" onClick={onFermer}>
      <div className="modale" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Reçu de paiement">
        <Recu recu={recu} />

        {recu.annule && (
          <div className="form-actions no-print">
            <span className="muted">Pour corriger, encaissez à nouveau le bon montant :</span>
            <Link className="btn btn-primaire" to={`/paiements/nouveau?inscription=${recu.inscription_id}`} onClick={onFermer}>
              Encaisser à nouveau
            </Link>
          </div>
        )}

        {annulation ? (
          <form className="annulation no-print" onSubmit={annuler}>
            <Alerte type="erreur">{erreur}</Alerte>
            <p className="muted petit">
              Le reçu restera visible dans le journal, marqué <strong>ANNULÉ</strong>, et les éléments payés redeviendront à
              payer. Cette action ne peut pas être défaite.
            </p>
            <Champ label="Motif de l'annulation" requis>
              <input
                autoFocus
                value={motif}
                onChange={(e) => setMotif(e.target.value)}
                placeholder="Ex. : erreur de montant, mauvais élève…"
                maxLength={255}
              />
            </Champ>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setAnnulation(false)}>
                Retour
              </button>
              <button className="btn btn-danger-plein" disabled={envoi || motif.trim().length < 3}>
                {envoi ? 'Annulation…' : 'Confirmer l’annulation'}
              </button>
            </div>
          </form>
        ) : (
          <div className="form-actions no-print">
            {!recu.annule && (
              <button className="btn btn-danger btn-gauche" onClick={() => setAnnulation(true)}>
                Annuler ce reçu
              </button>
            )}
            <button className="btn btn-ghost" onClick={onFermer}>
              Fermer
            </button>
            <button className="btn btn-primaire" onClick={() => onImprimer(recu.numero_recu)}>
              Imprimer le reçu
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
