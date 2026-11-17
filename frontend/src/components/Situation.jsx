import { ar, moisCourt, nomMois } from '../api'

const dateCourte = (iso) => {
  const [, m, j] = iso.split('-')
  return `${j}/${m}`
}

/**
 * Situation financière d'une inscription : droit + grille des mois (septembre → juin).
 * Chaque mois indique son échéance (au plus tard le 10) ou « En retard » si elle est dépassée.
 */
export default function Situation({ situation, selection, onBasculer, droitSelectionne, onBasculerDroit }) {
  if (!situation) return null
  const interactif = Boolean(onBasculer)
  const pct = situation.total_du ? Math.round((situation.total_paye / situation.total_du) * 100) : 0

  return (
    <div className="situation">
      <div className="situation-totaux">
        <div>
          <span className="muted">Total dû</span>
          <strong>{ar(situation.total_du)}</strong>
        </div>
        <div>
          <span className="muted">Payé</span>
          <strong className="ok">{ar(situation.total_paye)}</strong>
        </div>
        <div>
          <span className="muted">Reste</span>
          <strong className={situation.reste > 0 ? 'danger' : 'ok'}>{ar(situation.reste)}</strong>
        </div>
      </div>
      <div className="progression" aria-label={`${pct}% payé`}>
        <div style={{ width: `${pct}%` }} />
      </div>

      {situation.mois_en_retard > 0 && (
        <div className="alerte alerte-erreur alerte-compacte" role="status">
          <span>
            ⚠ {situation.mois_en_retard} mois d'écolage en retard ({ar(situation.montant_en_retard)}) — l'écolage est
            dû au plus tard le 10 de chaque mois.
          </span>
        </div>
      )}

      <div className="grille-mois">
        <button
          type="button"
          className={`case case-droit ${situation.droit_paye ? 'payee' : ''} ${droitSelectionne ? 'choisie' : ''}`}
          disabled={!interactif || situation.droit_paye}
          onClick={onBasculerDroit}
          title={situation.droit_paye ? 'Droit déjà payé' : `Droit d'inscription : ${ar(situation.droit_inscription)}`}
        >
          <span className="case-titre">Droit</span>
          <span className="case-montant">{ar(situation.droit_inscription)}</span>
          <span className="case-etat">{situation.droit_paye ? '✓ Payé' : droitSelectionne ? 'Sélectionné' : 'À payer'}</span>
        </button>

        {situation.mois.map(({ mois, paye, echeance, en_retard }) => {
          const choisie = selection?.includes(mois)
          const etat = paye ? '✓ Payé' : choisie ? 'Sélectionné' : en_retard ? 'En retard' : `Avant le ${dateCourte(echeance)}`
          return (
            <button
              type="button"
              key={mois}
              className={`case ${paye ? 'payee' : ''} ${en_retard ? 'retard' : ''} ${choisie ? 'choisie' : ''}`}
              disabled={!interactif || paye}
              onClick={() => onBasculer(mois)}
              title={`${nomMois(mois)} — ${
                paye ? 'payé' : `${ar(situation.ecolage_mensuel)}, à payer au plus tard le ${dateCourte(echeance)}`
              }`}
            >
              <span className="case-titre">{moisCourt(mois)}</span>
              <span className="case-montant">{mois.slice(0, 4)}</span>
              <span className="case-etat">{etat}</span>
            </button>
          )
        })}
      </div>
    </div>
  )
}
