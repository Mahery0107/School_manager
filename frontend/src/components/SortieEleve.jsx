import { useState } from 'react'
import { api, aujourdhui, lireErreur, MOTIFS_SORTIE, nomComplet } from '../api'
import { Alerte, Champ } from './ui'

/**
 * Retirer un élève de l'école (renvoi, départ…) : rien n'est effacé, l'élève sort de la liste des présents
 * et les écolages après son départ ne sont plus dus.
 * Si l'inscription n'a jamais reçu de paiement (inscription faite par erreur), elle peut aussi être supprimée.
 */
export default function SortieEleve({ eleve, inscription, onFermer, onTermine }) {
  const [form, setForm] = useState({ motif_sortie: 'renvoye', date_sortie: aujourdhui(), remarque_sortie: '' })
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const suppressionPossible = inscription.paiements.length === 0

  const maj = (champ) => (e) => setForm({ ...form, [champ]: e.target.value })

  const retirer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    setMessage('')
    try {
      await api.post(`/inscriptions/${inscription.id}/sortie`, { ...form, remarque_sortie: form.remarque_sortie || null })
      onTermine({ texte: `${nomComplet(eleve)} a été retiré(e) de la liste des élèves présents.` })
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreurs(champs)
      setMessage(message)
    } finally {
      setEnvoi(false)
    }
  }

  const supprimer = async () => {
    if (
      !confirm(
        `Supprimer définitivement l'inscription ${inscription.annee_scolaire.libelle} de ${nomComplet(eleve)} ?\n\n` +
          'À utiliser seulement pour une inscription faite par erreur. Cette action ne peut pas être annulée.',
      )
    )
      return
    setEnvoi(true)
    try {
      const r = await api.delete(`/inscriptions/${inscription.id}`)
      onTermine({ texte: 'Inscription supprimée.', eleveSupprime: r.data.eleve_supprime })
    } catch (err) {
      setMessage(lireErreur(err).message)
      setEnvoi(false)
    }
  }

  return (
    <div className="modale-fond" onClick={onFermer}>
      <form className="modale" onClick={(e) => e.stopPropagation()} onSubmit={retirer} role="dialog" aria-label="Retirer l'élève">
        <h3 className="carte-titre">Retirer {nomComplet(eleve)} de l'école</h3>
        <p className="muted petit">
          {inscription.annee_scolaire.libelle} — {inscription.classe.nom}. L'élève n'apparaîtra plus dans la liste des
          présents ni dans l'effectif. <strong>Son historique et ses reçus sont conservés</strong>, les écolages après
          son départ ne sont plus dus, et ce qui reste à payer avant son départ reste dû. Vous pourrez le réintégrer
          plus tard si besoin.
        </p>
        <Alerte onFermer={() => setMessage('')}>{message}</Alerte>

        <div className="grille-champs">
          <Champ label="Motif" requis erreur={erreurs.motif_sortie}>
            <select value={form.motif_sortie} onChange={maj('motif_sortie')}>
              {Object.entries(MOTIFS_SORTIE).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Champ>
          <Champ label="Date de départ" requis erreur={erreurs.date_sortie}>
            <input type="date" value={form.date_sortie} max={aujourdhui()} onChange={maj('date_sortie')} />
          </Champ>
          <Champ label="Remarque" large erreur={erreurs.remarque_sortie}>
            <input
              value={form.remarque_sortie}
              onChange={maj('remarque_sortie')}
              placeholder="Ex. : décision du conseil de discipline, déménagement à Fianarantsoa…"
              maxLength={255}
            />
          </Champ>
        </div>

        <div className="form-actions">
          {suppressionPossible && (
            <button type="button" className="btn btn-danger btn-gauche" onClick={supprimer} disabled={envoi}>
              Supprimer (inscription par erreur)
            </button>
          )}
          <button type="button" className="btn btn-ghost" onClick={onFermer}>
            Annuler
          </button>
          <button className="btn btn-danger-plein" disabled={envoi}>
            {envoi ? 'Enregistrement…' : "Retirer de l'école"}
          </button>
        </div>
      </form>
    </div>
  )
}
