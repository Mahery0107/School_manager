import { useEffect, useState } from 'react'
import { api, ar, lireErreur } from '../api'
import { Alerte, Badge, Champ } from './ui'

const OBLIGATOIRE = { tous: 'Tous les élèves', nouveaux: 'Nouveaux élèves', aucun: 'Personne (sur demande)' }
const VIDE = {
  id: null,
  libelle: '',
  montant: '',
  portee: 'eleve',
  obligatoire: 'tous',
  facultatif_autres: false,
  actif: true,
  classe_ids: [],
}

/** Paramètres → Frais de l'année (en plus de l'écolage) : montants, classes concernées, règles. */
export default function FraisParametres({ classes }) {
  const [liste, setListe] = useState([])
  const [form, setForm] = useState(VIDE)
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState({ type: 'erreur', texte: '' })

  const charger = () => api.get('/frais').then((r) => setListe(r.data))
  useEffect(() => {
    charger()
  }, [])

  const nomsClasses = (ids) =>
    ids.length === 0 ? 'Toutes les classes' : classes.filter((c) => ids.includes(c.id)).map((c) => c.nom).join(', ')

  const basculerClasse = (id) =>
    setForm((f) => ({
      ...f,
      classe_ids: f.classe_ids.includes(id) ? f.classe_ids.filter((x) => x !== id) : [...f.classe_ids, id],
    }))

  const enregistrer = async (e) => {
    e.preventDefault()
    try {
      if (form.id) await api.put(`/frais/${form.id}`, form)
      else await api.post('/frais', form)
      setErreurs({})
      setMessage({ type: 'succes', texte: `Frais « ${form.libelle} » enregistré.` })
      setForm(VIDE)
      charger()
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreurs(champs)
      setMessage({ type: 'erreur', texte: message })
    }
  }

  const supprimer = async (f) => {
    if (!confirm(`Supprimer le frais « ${f.libelle} » ?`)) return
    try {
      await api.delete(`/frais/${f.id}`)
      setMessage({ type: 'succes', texte: `Frais « ${f.libelle} » supprimé.` })
      charger()
    } catch (err) {
      setMessage({ type: 'erreur', texte: lireErreur(err).message })
    }
  }

  return (
    <section className="carte">
      <h3 className="carte-titre">Frais de l'année (en plus de l'écolage)</h3>
      <p className="muted petit">
        Un frais déjà commencé garde son montant ; un changement de montant s'applique aux paiements suivants. Les
        élèves dont l'ancien « droit d'inscription » est payé ne doivent pas ces frais pour cette année.
      </p>
      <Alerte type={message.type} onFermer={() => setMessage({ ...message, texte: '' })}>
        {message.texte}
      </Alerte>

      <div className="table-scroll">
        <table className="table">
          <thead>
            <tr>
              <th>Frais</th>
              <th className="num">Montant</th>
              <th>Dû par</th>
              <th>Classes</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {liste.map((f) => (
              <tr key={f.id} className={f.actif ? '' : 'ligne-sortie'}>
                <td>
                  {f.libelle} {!f.actif && <Badge>Inactif</Badge>}
                </td>
                <td className="num">
                  {ar(f.montant)}
                  {f.portee === 'famille' && <small className="bloc muted">par famille</small>}
                </td>
                <td>
                  {OBLIGATOIRE[f.obligatoire]}
                  {f.facultatif_autres && <small className="bloc muted">facultatif pour les autres</small>}
                </td>
                <td className="muted">{nomsClasses(f.classe_ids)}</td>
                <td className="num actions-ligne">
                  <button
                    className="btn btn-petit"
                    onClick={() => {
                      setErreurs({})
                      setForm({ ...VIDE, ...f })
                    }}
                  >
                    Modifier
                  </button>
                  <button className="btn btn-petit btn-danger" onClick={() => supprimer(f)}>
                    Suppr.
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <form onSubmit={enregistrer} className="sous-formulaire">
        <h4>{form.id ? `Modifier « ${form.libelle} »` : 'Nouveau frais'}</h4>
        <div className="grille-champs">
          <Champ label="Libellé" requis erreur={erreurs.libelle}>
            <input value={form.libelle} onChange={(e) => setForm({ ...form, libelle: e.target.value })} />
          </Champ>
          <Champ label="Montant (Ar)" requis erreur={erreurs.montant}>
            <input
              type="number"
              min="0"
              value={form.montant}
              onChange={(e) => setForm({ ...form, montant: e.target.value })}
            />
          </Champ>
          <Champ label="Obligatoire pour" requis erreur={erreurs.obligatoire}>
            <select value={form.obligatoire} onChange={(e) => setForm({ ...form, obligatoire: e.target.value })}>
              {Object.entries(OBLIGATOIRE).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Champ>
          <Champ label="Payé" requis erreur={erreurs.portee}>
            <select value={form.portee} onChange={(e) => setForm({ ...form, portee: e.target.value })}>
              <option value="eleve">Par élève</option>
              <option value="famille">Une fois par famille (frères et sœurs)</option>
            </select>
          </Champ>
        </div>

        <label className="case-a-cocher">
          <input
            type="checkbox"
            checked={form.facultatif_autres}
            onChange={(e) => setForm({ ...form, facultatif_autres: e.target.checked })}
          />
          Les autres élèves peuvent aussi le payer à la demande (ex. t-shirt à changer)
        </label>
        <label className="case-a-cocher">
          <input type="checkbox" checked={form.actif} onChange={(e) => setForm({ ...form, actif: e.target.checked })} />
          Actif (appliqué aux élèves)
        </label>

        <fieldset className="classes-frais">
          <legend>
            Classes concernées <span className="muted">(aucune cochée = toutes les classes)</span>
          </legend>
          {classes.map((c) => (
            <label key={c.id} className="case-a-cocher">
              <input type="checkbox" checked={form.classe_ids.includes(c.id)} onChange={() => basculerClasse(c.id)} />
              {c.nom}
            </label>
          ))}
          {erreurs.classe_ids && <span className="champ-erreur">{erreurs.classe_ids}</span>}
        </fieldset>

        <div className="form-actions">
          {form.id && (
            <button type="button" className="btn btn-ghost" onClick={() => setForm(VIDE)}>
              Annuler
            </button>
          )}
          <button className="btn btn-primaire">{form.id ? 'Enregistrer' : 'Ajouter le frais'}</button>
        </div>
      </form>
    </section>
  )
}
