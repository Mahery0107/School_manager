import { useState } from 'react'
import { api, ar, dateFr, lireErreur } from '../api'
import CompteAdmin from '../components/CompteAdmin'
import Sauvegardes from '../components/Sauvegardes'
import { Alerte, Badge, Champ, EnTete } from '../components/ui'
import { useReferentiels } from '../hooks'

const CLASSE_VIDE = { id: null, nom: '', ordre: '', droit_inscription: '', ecolage_mensuel: '' }
const ANNEE_VIDE = { libelle: '', date_debut: '', date_fin: '', active: false }

export default function Parametres({ utilisateur, onCompteModifie }) {
  const { classes, annees, recharger } = useReferentiels()
  const [classe, setClasse] = useState(CLASSE_VIDE)
  const [annee, setAnnee] = useState(ANNEE_VIDE)
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState({ type: 'erreur', texte: '' })

  const echec = (err) => {
    const { message, champs } = lireErreur(err)
    setErreurs(champs)
    setMessage({ type: 'erreur', texte: message })
  }
  const succes = (texte) => {
    setErreurs({})
    setMessage({ type: 'succes', texte })
    recharger()
  }

  const enregistrerClasse = async (e) => {
    e.preventDefault()
    try {
      if (classe.id) await api.put(`/classes/${classe.id}`, classe)
      else await api.post('/classes', classe)
      succes(`Classe « ${classe.nom} » enregistrée.`)
      setClasse(CLASSE_VIDE)
    } catch (err) {
      echec(err)
    }
  }

  const supprimerClasse = async (c) => {
    if (!confirm(`Supprimer la classe ${c.nom} ?`)) return
    try {
      await api.delete(`/classes/${c.id}`)
      succes(`Classe « ${c.nom} » supprimée.`)
    } catch (err) {
      echec(err)
    }
  }

  const enregistrerAnnee = async (e) => {
    e.preventDefault()
    try {
      await api.post('/annees-scolaires', annee)
      succes(`Année ${annee.libelle} créée.`)
      setAnnee(ANNEE_VIDE)
    } catch (err) {
      echec(err)
    }
  }

  const activer = async (a) => {
    try {
      await api.post(`/annees-scolaires/${a.id}/activer`)
      succes(`L'année ${a.libelle} est maintenant l'année en cours.`)
    } catch (err) {
      echec(err)
    }
  }

  return (
    <>
      <EnTete titre="Paramètres" sousTitre="Années scolaires, classes et tarifs, compte administrateur, sauvegardes." />
      <Alerte type={message.type} onFermer={() => setMessage({ ...message, texte: '' })}>
        {message.texte}
      </Alerte>

      <div className="deux-colonnes">
        <section className="carte">
          <h3 className="carte-titre">Années scolaires</h3>
          <table className="table">
            <tbody>
              {annees.map((a) => (
                <tr key={a.id}>
                  <td>
                    <strong>{a.libelle}</strong>
                    <div className="muted">
                      {dateFr(a.date_debut)} → {dateFr(a.date_fin)} · {a.mois.length} mois
                    </div>
                  </td>
                  <td className="num">
                    {a.active ? (
                      <Badge ton="ok">En cours</Badge>
                    ) : (
                      <button className="btn btn-petit" onClick={() => activer(a)}>
                        Activer
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <form onSubmit={enregistrerAnnee} className="sous-formulaire">
            <h4>Nouvelle année</h4>
            <div className="grille-champs">
              <Champ label="Libellé" requis erreur={erreurs.libelle}>
                <input
                  placeholder="2027-2028"
                  value={annee.libelle}
                  onChange={(e) => {
                    // Année scolaire de l'établissement : du 1er septembre au 30 juin
                    const libelle = e.target.value
                    const m = libelle.match(/^(\d{4})-(\d{4})$/)
                    setAnnee({
                      ...annee,
                      libelle,
                      ...(m && { date_debut: `${m[1]}-09-01`, date_fin: `${m[2]}-06-30` }),
                    })
                  }}
                />
              </Champ>
              <Champ label="Début" requis erreur={erreurs.date_debut}>
                <input type="date" value={annee.date_debut} onChange={(e) => setAnnee({ ...annee, date_debut: e.target.value })} />
              </Champ>
              <Champ label="Fin" requis erreur={erreurs.date_fin}>
                <input type="date" value={annee.date_fin} onChange={(e) => setAnnee({ ...annee, date_fin: e.target.value })} />
              </Champ>
            </div>
            <label className="case-a-cocher">
              <input type="checkbox" checked={annee.active} onChange={(e) => setAnnee({ ...annee, active: e.target.checked })} />
              Définir comme année en cours
            </label>
            <div className="form-actions">
              <button className="btn btn-primaire">Créer l'année</button>
            </div>
          </form>
        </section>

        <section className="carte">
          <h3 className="carte-titre">Classes et tarifs</h3>
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>Classe</th>
                  <th className="num">Droit</th>
                  <th className="num">Écolage / mois</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {classes.map((c) => (
                  <tr key={c.id}>
                    <td>{c.nom}</td>
                    <td className="num">{ar(c.droit_inscription)}</td>
                    <td className="num">{ar(c.ecolage_mensuel)}</td>
                    <td className="num actions-ligne">
                      <button className="btn btn-petit" onClick={() => setClasse(c)}>
                        Modifier
                      </button>
                      <button className="btn btn-petit btn-danger" onClick={() => supprimerClasse(c)}>
                        Suppr.
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <form onSubmit={enregistrerClasse} className="sous-formulaire">
            <h4>{classe.id ? `Modifier ${classe.nom}` : 'Nouvelle classe'}</h4>
            <div className="grille-champs">
              <Champ label="Nom" requis erreur={erreurs.nom}>
                <input value={classe.nom} onChange={(e) => setClasse({ ...classe, nom: e.target.value })} />
              </Champ>
              <Champ label="Ordre (niveau)" requis erreur={erreurs.ordre}>
                <input type="number" min="0" value={classe.ordre} onChange={(e) => setClasse({ ...classe, ordre: e.target.value })} />
              </Champ>
              <Champ label="Droit d'inscription (Ar)" requis erreur={erreurs.droit_inscription}>
                <input
                  type="number"
                  min="0"
                  value={classe.droit_inscription}
                  onChange={(e) => setClasse({ ...classe, droit_inscription: e.target.value })}
                />
              </Champ>
              <Champ label="Écolage mensuel (Ar)" requis erreur={erreurs.ecolage_mensuel}>
                <input
                  type="number"
                  min="0"
                  value={classe.ecolage_mensuel}
                  onChange={(e) => setClasse({ ...classe, ecolage_mensuel: e.target.value })}
                />
              </Champ>
            </div>
            <div className="form-actions">
              {classe.id && (
                <button type="button" className="btn btn-ghost" onClick={() => setClasse(CLASSE_VIDE)}>
                  Annuler
                </button>
              )}
              <button className="btn btn-primaire">{classe.id ? 'Enregistrer' : 'Ajouter la classe'}</button>
            </div>
          </form>
        </section>
      </div>

      <div className="deux-colonnes">
        <CompteAdmin utilisateur={utilisateur} onModifie={onCompteModifie} />
        <Sauvegardes />
      </div>
    </>
  )
}
