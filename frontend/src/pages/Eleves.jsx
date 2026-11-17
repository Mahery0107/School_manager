import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, nomComplet, nomMois } from '../api'
import { Badge, EnTete, Pagination, Vide } from '../components/ui'
import { useDebounce, useReferentiels } from '../hooks'

const STATUTS = {
  paye: { libelle: 'Payé', ton: 'ok' },
  a_payer: { libelle: 'À payer', ton: 'attente' },
  en_retard: { libelle: 'En retard', ton: 'danger' },
}

const dateCourte = (iso) => iso && `${iso.slice(8, 10)}/${iso.slice(5, 7)}`

/** Statut de l'écolage du mois choisi pour un élève (+ autres mois en retard). */
function StatutEcolage({ ligne }) {
  const e = ligne.ecolage_mois
  if (!e) return <span className="muted">—</span>
  const s = STATUTS[e.statut]
  const autresRetards = ligne.mois_en_retard - (e.statut === 'en_retard' ? 1 : 0)
  return (
    <div className="statut-ecolage">
      <Badge ton={s.ton}>
        {e.statut === 'paye' ? '✓ ' : e.statut === 'en_retard' ? '⚠ ' : ''}
        {s.libelle}
      </Badge>
      {e.statut === 'a_payer' && <small className="muted">avant le {dateCourte(e.echeance)}</small>}
      {autresRetards > 0 && (
        <small className="danger">
          + {autresRetards} autre{autresRetards > 1 ? 's' : ''} mois en retard
        </small>
      )}
    </div>
  )
}

/** Liste des élèves inscrits pour une année scolaire, avec le statut de l'écolage du mois. */
export default function Eleves() {
  const { classes, annees, anneeActive } = useReferentiels()
  const [anneeId, setAnneeId] = useState('')
  const [classeId, setClasseId] = useState('')
  const [mois, setMois] = useState('')
  const [statut, setStatut] = useState('')
  const [q, setQ] = useState('')
  const [numPage, setNumPage] = useState(1)
  const [page, setPage] = useState(null)
  const qd = useDebounce(q)

  const annee = annees.find((a) => String(a.id) === String(anneeId))

  useEffect(() => {
    if (anneeActive && !anneeId) setAnneeId(String(anneeActive.id))
  }, [anneeActive]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => setNumPage(1), [qd, anneeId, classeId, mois, statut])

  useEffect(() => {
    if (!anneeId) return
    api
      .get('/inscriptions', {
        params: { annee_scolaire_id: anneeId, classe_id: classeId, q: qd, page: numPage, mois, statut },
      })
      .then((r) => {
        setPage(r.data)
        // le serveur choisit le mois par défaut (mois en cours) : on l'affiche dans le sélecteur
        if (!mois && r.data.mois) setMois(r.data.mois)
      })
  }, [anneeId, classeId, qd, numPage, mois, statut]) // eslint-disable-line react-hooks/exhaustive-deps

  const changerAnnee = (id) => {
    setAnneeId(id)
    setMois('') // revient au mois par défaut de la nouvelle année
  }

  const moisAffiche = page?.mois || mois

  return (
    <>
      <EnTete titre="Élèves inscrits" sousTitre={page ? `${page.total} élève(s)` : ''}>
        <Link className="btn btn-ghost" to="/reinscription">
          Réinscrire
        </Link>
        <Link className="btn btn-primaire" to="/inscription">
          + Nouvel élève
        </Link>
      </EnTete>

      <div className="carte filtres">
        <input type="search" placeholder="Nom, prénom ou matricule…" value={q} onChange={(e) => setQ(e.target.value)} />
        <select value={anneeId} onChange={(e) => changerAnnee(e.target.value)} aria-label="Année scolaire">
          {annees.map((a) => (
            <option key={a.id} value={a.id}>
              {a.libelle}
            </option>
          ))}
        </select>
        <select value={classeId} onChange={(e) => setClasseId(e.target.value)} aria-label="Classe">
          <option value="">Toutes les classes</option>
          {classes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </select>
        <select value={mois} onChange={(e) => setMois(e.target.value)} aria-label="Mois d'écolage">
          {(annee?.mois || []).map((m) => (
            <option key={m} value={m}>
              Écolage {nomMois(m)}
            </option>
          ))}
        </select>
        <select value={statut} onChange={(e) => setStatut(e.target.value)} aria-label="Statut de l'écolage">
          <option value="">Tous les statuts</option>
          <option value="paye">Payé</option>
          <option value="non_paye">Non payé</option>
          <option value="a_payer">À payer (avant le 10)</option>
          <option value="en_retard">En retard</option>
        </select>
      </div>

      <div className="carte sans-padding">
        {page && page.data.length === 0 ? (
          <Vide>Aucun élève pour ces critères.</Vide>
        ) : (
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>Matricule</th>
                  <th>Nom et prénom(s)</th>
                  <th>Classe</th>
                  <th>Type</th>
                  <th>Écolage {moisAffiche ? nomMois(moisAffiche) : ''}</th>
                  <th>Contact parents</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {page?.data.map((i) => (
                  <tr key={i.id}>
                    <td className="mono">{i.etudiant.matricule}</td>
                    <td>
                      <Link to={`/eleves/${i.etudiant.id}`}>{nomComplet(i.etudiant)}</Link>
                      {!i.droit_paye && <small className="danger bloc">Droit d'inscription non payé</small>}
                    </td>
                    <td>{i.classe.nom}</td>
                    <td>
                      {i.type === 'inscription' ? <Badge ton="info">Nouveau</Badge> : <Badge>Réinscrit</Badge>}
                    </td>
                    <td>
                      <StatutEcolage ligne={i} />
                    </td>
                    <td className="muted">
                      {[i.etudiant.telephone_pere, i.etudiant.telephone_mere].filter(Boolean).join(' / ')}
                    </td>
                    <td className="num">
                      <Link className="btn btn-petit" to={`/paiements/nouveau?inscription=${i.id}`}>
                        Payer
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <Pagination page={page} setPage={setNumPage} />
    </>
  )
}
