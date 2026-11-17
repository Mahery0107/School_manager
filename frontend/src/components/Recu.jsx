import { ar, dateFr, MODES, nomComplet, nomMois } from '../api'

/** Reçu de paiement imprimable. */
export default function Recu({ recu }) {
  if (!recu) return null
  return (
    <div className="recu">
      <div className="recu-entete">
        <div className="recu-identite">
          <img className="recu-logo" src="/logo.svg" alt="Logo de l'école" />
          <div>
            <div className="recu-ecole">{recu.ecole}</div>
            <div className="muted">Reçu de paiement — Année {recu.annee_scolaire}</div>
          </div>
        </div>
        <div className="recu-numero">
          <span className="muted">N°</span> <span className="mono">{recu.numero_recu}</span>
          <div className="muted">{dateFr(recu.date_paiement)}</div>
        </div>
      </div>

      {recu.annule && (
        <div className="recu-annule">
          <strong>REÇU ANNULÉ</strong> le {dateFr(recu.annule_le.slice(0, 10))} à {recu.annule_le.slice(11, 16)}
          {recu.annule_par && ` par ${recu.annule_par}`} — motif : {recu.annule_motif}
        </div>
      )}

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
        {recu.encaisse_par && (
          <>
            <dt>Encaissé par</dt>
            <dd>{recu.encaisse_par}</dd>
          </>
        )}
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
              <td>
                {l.type === 'droit' ? "Droit d'inscription" : `Écolage — ${nomMois(l.mois)}`}
                {l.reste_apres > 0 ? (
                  <small className="bloc muted">
                    Versement partiel sur {ar(l.montant_du)}
                    {l.deja_paye > 0 && ` (déjà versé : ${ar(l.deja_paye)})`} —{' '}
                    <span className="danger">reste {ar(l.reste_apres)}</span>
                  </small>
                ) : (
                  l.deja_paye > 0 && <small className="bloc muted">Solde — {ar(l.montant_du)} entièrement payé</small>
                )}
              </td>
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
