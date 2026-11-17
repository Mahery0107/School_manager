import { useEffect, useState } from 'react'
import { api, nomComplet } from '../api'
import { useDebounce } from '../hooks'

/**
 * Champ de recherche avec liste de résultats.
 * source = 'etudiants' (tous les élèves) ou 'inscriptions' (inscrits de l'année active).
 */
export default function RechercheEleve({ source = 'etudiants', onChoisir, placeholder, rendreDetail }) {
  const [q, setQ] = useState('')
  const [resultats, setResultats] = useState([])
  const [charge, setCharge] = useState(false)
  const qd = useDebounce(q)

  useEffect(() => {
    if (qd.trim().length < 2) {
      setResultats([])
      return
    }
    let actif = true
    setCharge(true)
    api
      .get(`/${source}`, { params: { q: qd, per_page: 8 } })
      .then((r) => actif && setResultats(r.data.data))
      .finally(() => actif && setCharge(false))
    return () => {
      actif = false
    }
  }, [qd, source])

  return (
    <div className="recherche">
      <input
        type="search"
        autoFocus
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder={placeholder || 'Rechercher par nom, prénom ou matricule…'}
      />
      {qd.trim().length >= 2 && (
        <ul className="recherche-resultats">
          {charge && <li className="muted">Recherche…</li>}
          {!charge && resultats.length === 0 && <li className="muted">Aucun élève trouvé.</li>}
          {!charge &&
            resultats.map((r) => {
              const e = source === 'etudiants' ? r : r.etudiant
              return (
                <li key={r.id}>
                  <button
                    type="button"
                    onClick={() => {
                      onChoisir(r)
                      setQ('')
                    }}
                  >
                    <strong>{nomComplet(e)}</strong>
                    <span className="mono muted">{e.matricule}</span>
                    {rendreDetail && <span className="muted">{rendreDetail(r)}</span>}
                  </button>
                </li>
              )
            })}
        </ul>
      )}
    </div>
  )
}
