import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ar, dateFr, MODES, nomComplet } from '../api'
import ApercuPdf from '../components/ApercuPdf'
import ModaleRecu from '../components/ModaleRecu'
import { Badge, EnTete, Pagination, Vide } from '../components/ui'
import { useDebounce } from '../hooks'

export default function Journal() {
  const [q, setQ] = useState('')
  const [du, setDu] = useState('')
  const [au, setAu] = useState('')
  const [numPage, setNumPage] = useState(1)
  const [page, setPage] = useState(null)
  const [recu, setRecu] = useState(null)
  const [pdf, setPdf] = useState(null)
  const fermerPdf = useCallback(() => setPdf(null), [])
  const qd = useDebounce(q)

  useEffect(() => setNumPage(1), [qd, du, au])

  const [version, setVersion] = useState(0) // recharge la liste après une annulation
  useEffect(() => {
    api.get('/paiements', { params: { q: qd, du, au, page: numPage } }).then((r) => setPage(r.data))
  }, [qd, du, au, numPage, version])

  const ouvrir = (numero) => api.get(`/recus/${numero}`).then((r) => setRecu(r.data))
  const totalPage = page?.data.reduce((s, r) => s + (r.annule ? 0 : r.total), 0) || 0

  return (
    <>
      <EnTete titre="Journal des paiements" sousTitre="Tous les reçus émis.">
        <Link className="btn btn-primaire" to="/paiements/nouveau">
          + Encaisser
        </Link>
      </EnTete>

      <div className="carte filtres">
        <input type="search" placeholder="Élève, matricule ou n° de reçu…" value={q} onChange={(e) => setQ(e.target.value)} />
        <label>
          Du <input type="date" value={du} onChange={(e) => setDu(e.target.value)} />
        </label>
        <label>
          Au <input type="date" value={au} onChange={(e) => setAu(e.target.value)} />
        </label>
      </div>

      <div className="carte sans-padding">
        {page && page.data.length === 0 ? (
          <Vide>Aucun paiement trouvé.</Vide>
        ) : (
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>Reçu</th>
                  <th>Date</th>
                  <th>Élève</th>
                  <th>Classe</th>
                  <th>Mode</th>
                  <th className="num">Montant</th>
                </tr>
              </thead>
              <tbody>
                {page?.data.map((r) => (
                  <tr
                    key={r.numero_recu}
                    className={`cliquable ${r.annule ? 'ligne-annulee' : ''}`}
                    onClick={() => ouvrir(r.numero_recu)}
                  >
                    <td className="mono">
                      {r.numero_recu} {r.annule && <Badge ton="danger">Annulé</Badge>}
                    </td>
                    <td>{dateFr(r.date_paiement)}</td>
                    <td>{nomComplet(r.etudiant)}</td>
                    <td>{r.classe}</td>
                    <td>{MODES[r.mode]}</td>
                    <td className="num">{ar(r.total)}</td>
                  </tr>
                ))}
              </tbody>
              {page?.data.length > 0 && (
                <tfoot>
                  <tr>
                    <td colSpan={5}>Total de la page (hors reçus annulés)</td>
                    <td className="num">{ar(totalPage)}</td>
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
        )}
      </div>
      <Pagination page={page} setPage={setNumPage} />

      {recu && (
        <ModaleRecu
          recu={recu}
          onFermer={() => setRecu(null)}
          onImprimer={setPdf}
          onAnnule={(r) => {
            setRecu(r)
            setVersion((v) => v + 1)
          }}
        />
      )}

      {pdf && <ApercuPdf numero={pdf} onFermer={fermerPdf} />}
    </>
  )
}
