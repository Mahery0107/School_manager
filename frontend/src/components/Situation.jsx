import { ar, moisCourt, nomMois } from '../api'

const dateCourte = (iso) => {
  const [, m, j] = iso.split('-')
  return `${j}/${m}`
}

/**
 * Situation financière d'une inscription : droit + grille des mois (septembre → juin).
 * Chaque mois indique son échéance (au plus tard le 10) ou « En retard » si elle est dépassée.
 * Un élément peut être payé en plusieurs fois : « Partiel » + reste à payer tant qu'il n'est pas soldé.
 */
export default function Situation({ situation, selection, onBasculer, droitSelectionne, onBasculerDroit }) {
  if (!situation) return null
  const interactif = Boolean(onBasculer)
  const d = situation.droit
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
          className={`case case-droit ${d.solde ? 'payee' : d.partiel ? 'partielle' : ''} ${droitSelectionne ? 'choisie' : ''}`}
          disabled={!interactif || d.solde}
          onClick={onBasculerDroit}
          title={
            d.solde
              ? `Droit payé : ${ar(d.montant_du)}`
              : `Droit d'inscription : ${ar(d.montant_du)}${d.partiel ? ` — déjà versé ${ar(d.montant_paye)}, reste ${ar(d.reste)}` : ''}`
          }
        >
          <span className="case-titre">Droit</span>
          <span className="case-montant">{d.partiel ? `reste ${ar(d.reste)}` : ar(d.montant_du)}</span>
          <span className="case-etat">
            {d.solde ? '✓ Payé' : droitSelectionne ? 'Sélectionné' : d.partiel ? 'Partiel' : 'À payer'}
          </span>
        </button>

        {situation.mois.map(({ mois, paye, partiel, montant_du, montant_paye, reste, echeance, en_retard }) => {
          const choisie = selection?.includes(mois)
          const etat = paye
            ? '✓ Payé'
            : choisie
              ? 'Sélectionné'
              : en_retard
                ? partiel
                  ? 'Partiel · retard'
                  : 'En retard'
                : partiel
                  ? 'Partiel'
                  : `Avant le ${dateCourte(echeance)}`
          return (
            <button
              type="button"
              key={mois}
              className={`case ${paye ? 'payee' : ''} ${partiel ? 'partielle' : ''} ${en_retard ? 'retard' : ''} ${choisie ? 'choisie' : ''}`}
              disabled={!interactif || paye}
              onClick={() => onBasculer(mois)}
              title={`${nomMois(mois)} — ${
                paye
                  ? `payé (${ar(montant_du)})`
                  : partiel
                    ? `déjà versé ${ar(montant_paye)} sur ${ar(montant_du)}, reste ${ar(reste)} — avant le ${dateCourte(echeance)}`
                    : `${ar(montant_du)}, à payer au plus tard le ${dateCourte(echeance)}`
              }`}
            >
              <span className="case-titre">{moisCourt(mois)}</span>
              <span className="case-montant">{partiel ? `reste ${ar(reste)}` : mois.slice(0, 4)}</span>
              <span className="case-etat">{etat}</span>
            </button>
          )
        })}
      </div>
    </div>
  )
}
