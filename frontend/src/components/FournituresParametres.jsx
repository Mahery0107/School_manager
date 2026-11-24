import { useEffect, useState } from 'react'
import { api, lireErreur } from '../api'
import { Alerte, Champ } from './ui'

const VIDE = { designation: '', quantite: '', remarque: '' }

/**
 * Paramètres → Fournitures scolaires : liste par année et par classe
 * (ajout, modification, ordre, suppression, copie depuis une autre classe / l'an dernier).
 */
export default function FournituresParametres({ classes, annees }) {
  const anneeActive = annees.find((a) => a.active)
  const [anneeId, setAnneeId] = useState('')
  const [classeId, setClasseId] = useState('')
  const [liste, setListe] = useState([])
  const [nouveau, setNouveau] = useState(VIDE)
  const [edition, setEdition] = useState(null) // { id, designation, quantite, remarque }
  const [copie, setCopie] = useState({ annee: '', classe: '' })
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState({ type: 'erreur', texte: '' })

  // arrivée depuis le menu Fournitures (« Modifier les listes ») : on descend jusqu'à cette section
  useEffect(() => {
    if (window.location.hash === '#fournitures') {
      document.getElementById('fournitures')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }, [])

  // valeurs par défaut : année en cours, première classe
  useEffect(() => {
    if (!anneeId && anneeActive) setAnneeId(String(anneeActive.id))
    if (!classeId && classes.length) setClasseId(String(classes[0].id))
  }, [anneeActive, classes]) // eslint-disable-line react-hooks/exhaustive-deps

  const charger = () =>
    api
      .get('/fournitures', { params: { annee_scolaire_id: anneeId, classe_id: classeId } })
      .then((r) => setListe(r.data))

  useEffect(() => {
    if (anneeId && classeId) {
      setEdition(null)
      charger()
    }
  }, [anneeId, classeId]) // eslint-disable-line react-hooks/exhaustive-deps

  const echec = (err) => {
    const { message, champs } = lireErreur(err)
    setErreurs(champs)
    setMessage({ type: 'erreur', texte: message })
  }
  const succes = (texte) => {
    setErreurs({})
    setMessage({ type: 'succes', texte })
    charger()
  }

  const ajouter = async (e) => {
    e.preventDefault()
    try {
      await api.post('/fournitures', { ...nouveau, annee_scolaire_id: anneeId, classe_id: classeId })
      setNouveau(VIDE)
      succes(`« ${nouveau.designation} » ajouté.`)
    } catch (err) {
      echec(err)
    }
  }

  const enregistrer = async () => {
    try {
      await api.put(`/fournitures/${edition.id}`, edition)
      setEdition(null)
      succes('Fourniture modifiée.')
    } catch (err) {
      echec(err)
    }
  }

  const supprimer = async (f) => {
    if (!confirm(`Supprimer « ${f.designation} » de la liste ?`)) return
    try {
      await api.delete(`/fournitures/${f.id}`)
      succes(`« ${f.designation} » supprimé.`)
    } catch (err) {
      echec(err)
    }
  }

  const deplacer = async (index, sens) => {
    const ids = liste.map((f) => f.id)
    const cible = index + sens
    if (cible < 0 || cible >= ids.length) return
    ;[ids[index], ids[cible]] = [ids[cible], ids[index]]
    setListe(ids.map((id) => liste.find((f) => f.id === id)))
    try {
      await api.post('/fournitures/ordre', { ids })
    } catch (err) {
      echec(err)
    }
  }

  const copier = async () => {
    try {
      const r = await api.post('/fournitures/copier', {
        source_annee_id: copie.annee || anneeId,
        source_classe_id: copie.classe || classeId,
        annee_scolaire_id: anneeId,
        classe_id: classeId,
      })
      succes(`${r.data.copiees} fourniture(s) copiée(s) dans cette liste.`)
    } catch (err) {
      echec(err)
    }
  }

  const classe = classes.find((c) => String(c.id) === classeId)

  return (
    <section className="carte" id="fournitures">
      <h3 className="carte-titre">Fournitures scolaires</h3>
      <p className="muted petit">Une liste par année scolaire et par classe. Elle s'imprime depuis le menu « Fournitures ».</p>
      <Alerte type={message.type} onFermer={() => setMessage({ ...message, texte: '' })}>
        {message.texte}
      </Alerte>

      <div className="filtres-ligne">
        <select value={anneeId} onChange={(e) => setAnneeId(e.target.value)} aria-label="Année scolaire">
          {annees.map((a) => (
            <option key={a.id} value={a.id}>
              {a.libelle}
            </option>
          ))}
        </select>
        <select value={classeId} onChange={(e) => setClasseId(e.target.value)} aria-label="Classe">
          {classes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </select>
      </div>

      <div className="table-scroll">
        <table className="table">
          <thead>
            <tr>
              <th className="num">N°</th>
              <th>Désignation</th>
              <th>Quantité</th>
              <th>Remarque</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {liste.map((f, i) =>
              edition?.id === f.id ? (
                <tr key={f.id}>
                  <td className="num muted">{i + 1}</td>
                  <td>
                    <input value={edition.designation} onChange={(e) => setEdition({ ...edition, designation: e.target.value })} />
                  </td>
                  <td>
                    <input value={edition.quantite || ''} onChange={(e) => setEdition({ ...edition, quantite: e.target.value })} />
                  </td>
                  <td>
                    <input value={edition.remarque || ''} onChange={(e) => setEdition({ ...edition, remarque: e.target.value })} />
                  </td>
                  <td className="num actions-ligne">
                    <button className="btn btn-petit btn-primaire" onClick={enregistrer}>
                      Enregistrer
                    </button>
                    <button className="btn btn-petit btn-ghost" onClick={() => setEdition(null)}>
                      Annuler
                    </button>
                  </td>
                </tr>
              ) : (
                <tr key={f.id}>
                  <td className="num muted">{i + 1}</td>
                  <td>{f.designation}</td>
                  <td>{f.quantite}</td>
                  <td className="muted">{f.remarque}</td>
                  <td className="num actions-ligne">
                    <button className="btn btn-petit btn-ghost" onClick={() => deplacer(i, -1)} disabled={i === 0} title="Monter">
                      ↑
                    </button>
                    <button
                      className="btn btn-petit btn-ghost"
                      onClick={() => deplacer(i, 1)}
                      disabled={i === liste.length - 1}
                      title="Descendre"
                    >
                      ↓
                    </button>
                    <button className="btn btn-petit" onClick={() => setEdition({ ...f })}>
                      Modifier
                    </button>
                    <button className="btn btn-petit btn-danger" onClick={() => supprimer(f)}>
                      Suppr.
                    </button>
                  </td>
                </tr>
              ),
            )}
            {liste.length === 0 && (
              <tr>
                <td colSpan={5} className="muted">
                  Aucune fourniture pour {classe?.nom} : ajoutez-en ci-dessous, ou copiez une autre liste.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <form onSubmit={ajouter} className="sous-formulaire">
        <h4>Ajouter une fourniture{classe ? ` (${classe.nom})` : ''}</h4>
        <div className="grille-champs">
          <Champ label="Désignation" requis erreur={erreurs.designation}>
            <input
              value={nouveau.designation}
              onChange={(e) => setNouveau({ ...nouveau, designation: e.target.value })}
              placeholder="Ex. : Cahier 100 pages grand format"
            />
          </Champ>
          <Champ label="Quantité" erreur={erreurs.quantite}>
            <input
              value={nouveau.quantite}
              onChange={(e) => setNouveau({ ...nouveau, quantite: e.target.value })}
              placeholder="Ex. : 4, 1 rame, 2 paquets"
            />
          </Champ>
          <Champ label="Remarque" erreur={erreurs.remarque}>
            <input
              value={nouveau.remarque}
              onChange={(e) => setNouveau({ ...nouveau, remarque: e.target.value })}
              placeholder="Ex. : couvert en bleu"
            />
          </Champ>
        </div>
        <div className="form-actions">
          <button className="btn btn-primaire">Ajouter</button>
        </div>
      </form>

      <div className="sous-formulaire">
        <h4>Copier une liste existante dans {classe?.nom}</h4>
        <div className="filtres-ligne">
          <select value={copie.annee || anneeId} onChange={(e) => setCopie({ ...copie, annee: e.target.value })} aria-label="Année à copier">
            {annees.map((a) => (
              <option key={a.id} value={a.id}>
                {a.libelle}
              </option>
            ))}
          </select>
          <select value={copie.classe || classeId} onChange={(e) => setCopie({ ...copie, classe: e.target.value })} aria-label="Classe à copier">
            {classes.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </select>
          <button type="button" className="btn" onClick={copier}>
            Copier la liste
          </button>
        </div>
        {erreurs.source_classe_id && <p className="champ-erreur">{erreurs.source_classe_id}</p>}
        <p className="muted petit-aide">Ex. : reprendre la liste de l'an dernier, puis la modifier. Les articles sont ajoutés à la suite.</p>
      </div>
    </section>
  )
}
