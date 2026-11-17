import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ar, dateFr, lireErreur, MODES, MOTIFS_SORTIE, nomComplet, nomMois } from '../api'
import ChampsEleve, { ELEVE_VIDE } from '../components/ChampsEleve'
import Situation from '../components/Situation'
import SortieEleve from '../components/SortieEleve'
import { Alerte, Badge, EnTete } from '../components/ui'

export default function FicheEleve() {
  const { id } = useParams()
  const [eleve, setEleve] = useState(null)
  const [message, setMessage] = useState('')
  const [edition, setEdition] = useState(null)
  const [erreurs, setErreurs] = useState({})
  const [envoi, setEnvoi] = useState(false)
  const [succes, setSucces] = useState('')
  const [sortie, setSortie] = useState(null) // inscription à retirer de l'école
  const navigate = useNavigate()

  const reintegrer = async (i) => {
    if (!confirm(`Réintégrer ${nomComplet(eleve)} parmi les élèves présents (${i.annee_scolaire.libelle}) ?`)) return
    try {
      await api.delete(`/inscriptions/${i.id}/sortie`)
      await charger()
      setSucces('Élève réintégré : il apparaît de nouveau parmi les présents.')
    } catch (err) {
      setMessage(lireErreur(err).message)
    }
  }

  const charger = () =>
    api
      .get(`/etudiants/${id}`)
      .then((r) => setEleve(r.data))
      .catch((e) => setMessage(lireErreur(e).message))

  useEffect(() => {
    charger()
  }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

  const ouvrirEdition = () => {
    setErreurs({})
    setSucces('')
    setEdition(Object.fromEntries(Object.keys(ELEVE_VIDE).map((k) => [k, eleve[k] ?? ''])))
  }
  const maj = (champ) => (e) => setEdition({ ...edition, [champ]: e.target.value })

  const enregistrer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    try {
      // les champs vides sont envoyés à null (ex : parent non renseigné)
      const donnees = Object.fromEntries(Object.entries(edition).map(([k, v]) => [k, v === '' ? null : v]))
      await api.put(`/etudiants/${id}`, donnees)
      await charger()
      setEdition(null)
      setSucces('Informations enregistrées.')
    } catch (err) {
      setErreurs(lireErreur(err).champs)
    } finally {
      setEnvoi(false)
    }
  }

  if (message) return <Alerte>{message}</Alerte>
  if (!eleve) return <p className="muted">Chargement…</p>

  return (
    <>
      <EnTete titre={nomComplet(eleve)} sousTitre={`Matricule ${eleve.matricule}`}>
        {!edition && (
          <button className="btn btn-ghost" onClick={ouvrirEdition}>
            Modifier les informations
          </button>
        )}
        <Link className="btn btn-ghost" to="/reinscription">
          Réinscrire
        </Link>
      </EnTete>
      <Alerte type="succes" onFermer={() => setSucces('')}>
        {succes}
      </Alerte>

      {edition ? (
        <form className="carte formulaire" onSubmit={enregistrer} noValidate>
          <ChampsEleve form={edition} maj={maj} erreurs={erreurs} />
          <div className="form-actions">
            <button type="button" className="btn btn-ghost" onClick={() => setEdition(null)}>
              Annuler
            </button>
            <button className="btn btn-primaire" disabled={envoi}>
              {envoi ? 'Enregistrement…' : 'Enregistrer'}
            </button>
          </div>
        </form>
      ) : (
        <div className="carte">
          <dl className="infos-grille">
            <div>
              <dt>Sexe</dt>
              <dd>{eleve.sexe === 'F' ? 'Féminin' : 'Masculin'}</dd>
            </div>
            <div>
              <dt>Naissance</dt>
              <dd>
                {dateFr(eleve.date_naissance)} {eleve.lieu_naissance && `à ${eleve.lieu_naissance}`}
              </dd>
            </div>
            <div>
              <dt>Adresse</dt>
              <dd>{eleve.adresse || '—'}</dd>
            </div>
          </dl>
          <div className="parents parents-lecture">
            {[
              ['pere', 'Père'],
              ['mere', 'Mère'],
            ].map(([k, titre]) => (
              <div className="parent-bloc" key={k}>
                <h4>{titre}</h4>
                {eleve[`nom_${k}`] ? (
                  <dl className="infos-grille">
                    <div>
                      <dt>Nom</dt>
                      <dd>{eleve[`nom_${k}`]}</dd>
                    </div>
                    <div>
                      <dt>Profession</dt>
                      <dd>{eleve[`profession_${k}`] || '—'}</dd>
                    </div>
                    <div>
                      <dt>Téléphone</dt>
                      <dd>{eleve[`telephone_${k}`] || '—'}</dd>
                    </div>
                  </dl>
                ) : (
                  <p className="muted">Non renseigné</p>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      <h2 className="section-titre">Parcours et paiements</h2>
      {eleve.inscriptions.map((i) => (
        <div className="carte" key={i.id}>
          <div className="fiche-resume ligne">
            <div>
              <strong>
                {i.annee_scolaire.libelle} — {i.classe.nom}
              </strong>{' '}
              {i.type === 'inscription' ? <Badge ton="info">Inscription</Badge> : <Badge>Réinscription</Badge>}
              {i.annee_scolaire.active && !i.date_sortie && <Badge ton="ok">En cours</Badge>}
              {i.date_sortie && <Badge ton="danger">{MOTIFS_SORTIE[i.motif_sortie] || 'Sorti(e)'}</Badge>}
              <div className="muted">Inscrit(e) le {dateFr(i.date_inscription)}</div>
              {i.date_sortie && (
                <div className="danger">
                  A quitté l'école le {dateFr(i.date_sortie)}
                  {i.remarque_sortie && ` — ${i.remarque_sortie}`}
                </div>
              )}
            </div>
            <div className="page-actions">
              {i.date_sortie ? (
                <button className="btn btn-ghost" onClick={() => reintegrer(i)}>
                  Réintégrer
                </button>
              ) : (
                <button className="btn btn-danger" onClick={() => setSortie(i)}>
                  Retirer de l'école
                </button>
              )}
              {i.situation.reste > 0 && (
                <Link className="btn btn-primaire" to={`/paiements/nouveau?inscription=${i.id}`}>
                  Encaisser
                </Link>
              )}
            </div>
          </div>
          <Situation situation={i.situation} />
          {i.paiements.length > 0 && (
            <details className="historique">
              <summary>Historique des paiements ({i.paiements.length})</summary>
              <table className="table">
                <tbody>
                  {i.paiements.map((p) => (
                    <tr key={p.id}>
                      <td>{dateFr(p.date_paiement)}</td>
                      <td className="mono">{p.numero_recu}</td>
                      <td>{p.type === 'droit' ? "Droit d'inscription" : `Écolage ${nomMois(p.mois)}`}</td>
                      <td>{MODES[p.mode]}</td>
                      <td className="num">{ar(p.montant)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </details>
          )}
        </div>
      ))}

      {sortie && (
        <SortieEleve
          eleve={eleve}
          inscription={sortie}
          onFermer={() => setSortie(null)}
          onTermine={({ texte, eleveSupprime }) => {
            setSortie(null)
            if (eleveSupprime) {
              navigate('/eleves')
              return
            }
            charger()
            setSucces(texte)
          }}
        />
      )}
    </>
  )
}
