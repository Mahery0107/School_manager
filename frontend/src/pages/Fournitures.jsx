import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import ApercuPdf from '../components/ApercuPdf'
import { EnTete, Vide } from '../components/ui'
import { useReferentiels } from '../hooks'

/** Fournitures scolaires par classe : consultation et impression (une classe ou toutes les classes). */
export default function Fournitures() {
  const { classes, annees, anneeActive } = useReferentiels()
  const [anneeId, setAnneeId] = useState('')
  const [classeId, setClasseId] = useState('')
  const [liste, setListe] = useState(null)
  const [pdf, setPdf] = useState(false)
  const fermerPdf = useCallback(() => setPdf(false), [])

  useEffect(() => {
    if (anneeActive && !anneeId) setAnneeId(String(anneeActive.id))
  }, [anneeActive]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!anneeId) return
    api
      .get('/fournitures', { params: { annee_scolaire_id: anneeId, classe_id: classeId || undefined } })
      .then((r) => setListe(r.data))
  }, [anneeId, classeId])

  const annee = annees.find((a) => String(a.id) === anneeId)
  const classe = classes.find((c) => String(c.id) === classeId)
  // classes à afficher : celle choisie, ou toutes celles qui ont une liste
  const classesAffichees = classe ? [classe] : classes.filter((c) => liste?.some((f) => f.classe_id === c.id))

  return (
    <>
      <EnTete titre="Fournitures scolaires" sousTitre="Liste des fournitures demandées par classe.">
        <Link className="btn btn-ghost" to="/parametres#fournitures">
          Modifier les listes
        </Link>
        <button className="btn btn-primaire" onClick={() => setPdf(true)} disabled={!liste?.length}>
          Imprimer {classe ? `la liste ${classe.nom}` : 'toutes les listes'}
        </button>
      </EnTete>

      <div className="carte filtres">
        <select value={anneeId} onChange={(e) => setAnneeId(e.target.value)} aria-label="Année scolaire">
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
      </div>

      {liste && liste.length === 0 && (
        <div className="carte">
          <Vide>
            Aucune fourniture saisie {classe ? `pour la classe ${classe.nom}` : ''} en {annee?.libelle}.{' '}
            <Link to="/parametres#fournitures">Ajouter la liste dans Paramètres</Link>.
          </Vide>
        </div>
      )}

      <div className="fournitures-classes">
        {classesAffichees.map((c) => {
          const items = (liste || []).filter((f) => f.classe_id === c.id)
          return (
            <section className="carte sans-padding" key={c.id}>
              <div className="fournitures-entete">
                <h3>{c.nom}</h3>
                <span className="muted">{items.length} article(s)</span>
              </div>
              {items.length === 0 ? (
                <p className="muted fournitures-vide">Aucune fourniture pour cette classe.</p>
              ) : (
                <table className="table">
                  <thead>
                    <tr>
                      <th className="num">N°</th>
                      <th>Désignation</th>
                      <th>Quantité</th>
                      <th>Remarque</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((f, i) => (
                      <tr key={f.id}>
                        <td className="num muted">{i + 1}</td>
                        <td>{f.designation}</td>
                        <td>
                          <strong>{f.quantite}</strong>
                        </td>
                        <td className="muted">{f.remarque}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>
          )
        })}
      </div>

      {pdf && (
        <ApercuPdf
          chemin="/fournitures/pdf"
          params={{ annee_scolaire_id: anneeId, classe_id: classeId || undefined }}
          titre={`Fournitures ${annee?.libelle || ''}${classe ? ` — ${classe.nom}` : ''}`}
          fichier={`fournitures-${annee?.libelle || ''}${classe ? `-${classe.nom}` : ''}.pdf`}
          onFermer={fermerPdf}
        />
      )}
    </>
  )
}
