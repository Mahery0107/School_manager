import { ar, dateFr, MODES, nomComplet, nomMois } from '../api'

/** Reçu de paiement imprimable. */
export default function Recu({ recu }) {
  if (!recu) return null
  return (
    <div className="recu">
      <div className="recu-entete">
        <div>
          <div className="recu-ecole">{recu.ecole}</div>
          <div className="muted">Reçu de paiement — Année {recu.annee_scolaire}</div>
        </div>
        <div className="recu-numero">
          <span className="muted">N°</span> <span className="mono">{recu.numero_recu}</span>
          <div className="muted">{dateFr(recu.date_paiement)}</div>
        </div>
      </div>

      <dl className="recu-infos">
        <dt>Élève</dt>
        <dd>{nomComplet(recu.etudiant)}</dd>
        <dt>Matricule</dt>
        <dd className="mono">{recu.etudiant.matricule}</dd>
        <dt>Classe</dt>
        <dd>{recu.classe}</dd>
        <dt>Mode</dt>
        <dd>
          {MODES[recu.mode]}
          {recu.reference && <span className="muted"> · réf. {recu.reference}</span>}
        </dd>
      </dl>

      <table className="table recu-table">
        <thead>
          <tr>
            <th>Désignation</th>
            <th className="num">Montant</th>
          </tr>
        </thead>
        <tbody>
          {recu.lignes.map((l, i) => (
            <tr key={i}>
              <td>{l.type === 'droit' ? "Droit d'inscription" : `Écolage — ${nomMois(l.mois)}`}</td>
              <td className="num">{ar(l.montant)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <td>Total payé</td>
            <td className="num">{ar(recu.total)}</td>
          </tr>
        </tfoot>
      </table>

      <p className="muted recu-reste">
        Reste à payer sur l'année : <strong>{ar(recu.situation.reste)}</strong>
      </p>
      <div className="recu-signature">
        <span>Signature du caissier</span>
        <span>Signature du parent</span>
      </div>
    </div>
  )
}
