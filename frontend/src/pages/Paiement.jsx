import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, ar, aujourdhui, lireErreur, MODES, nomComplet, nomMois } from '../api'
import ApercuPdf from '../components/ApercuPdf'
import ModaleRecu from '../components/ModaleRecu'
import RechercheEleve from '../components/RechercheEleve'
import Situation from '../components/Situation'
import { Alerte, Champ, EnTete } from '../components/ui'

export default function Paiement() {
  const [params, setParams] = useSearchParams()
  const inscriptionId = params.get('inscription')
  const [inscription, setInscription] = useState(null)
  const [fraisChoisis, setFraisChoisis] = useState([]) // clés des frais sélectionnés (frais-ID, droit)
  const [mois, setMois] = useState([])
  // montant saisi par élément (clé 'frais-ID', 'droit' ou 'YYYY-MM') ; vide = tout le reste à payer
  const [montants, setMontants] = useState({})
  const [mode, setMode] = useState('especes')
  const [reference, setReference] = useState('')
  const [date, setDate] = useState(aujourdhui())
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [recu, setRecu] = useState(null)
  const [pdf, setPdf] = useState(null)
  const fermerPdf = useCallback(() => setPdf(null), [])

  const charger = (id) =>
    api
      .get(`/inscriptions/${id}`)
      .then((r) => setInscription(r.data))
      .catch((e) => setMessage(lireErreur(e).message))

  useEffect(() => {
    setFraisChoisis([])
    setMois([])
    setMontants({})
    setErreurs({})
    if (inscriptionId) charger(inscriptionId)
    else setInscription(null)
  }, [inscriptionId])

  const s = inscription?.situation
  // Éléments sélectionnés, avec leur reste à payer (paiement possible en plusieurs fois)
  const elements = s
    ? [
        ...s.frais.filter((f) => fraisChoisis.includes(f.cle)),
        ...mois.map((m) => ({ cle: m, libelle: `Écolage ${nomMois(m)}`, ...s.mois.find((x) => x.mois === m) })),
      ]
    : []
  const montantDe = (e) => (montants[e.cle] === undefined || montants[e.cle] === '' ? e.reste : Number(montants[e.cle]))
  const erreurMontant = (e) => {
    const v = montantDe(e)
    if (!Number.isInteger(v) || v <= 0) return 'Montant invalide'
    if (v > e.reste) return `Maximum ${ar(e.reste)}`
    return ''
  }
  const total = elements.reduce((t, e) => t + (montantDe(e) || 0), 0)
  const saisieInvalide = elements.some((e) => erreurMontant(e))
  const viderSelection = () => {
    setMois([])
    setFraisChoisis([])
    setMontants({})
  }

  const basculer = (m) => setMois((l) => (l.includes(m) ? l.filter((x) => x !== m) : [...l, m].sort()))

  /** Sélectionne les N prochains mois impayés, dans l'ordre. */
  const prochainsMois = (n) => setMois(s.mois.filter((x) => !x.paye).slice(0, n).map((x) => x.mois))

  const soumettre = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    setMessage('')
    try {
      const { data } = await api.post('/paiements', {
        inscription_id: inscription.id,
        droit: fraisChoisis.includes('droit'),
        frais: fraisChoisis.filter((c) => c.startsWith('frais-')).map((c) => Number(c.slice(6))),
        mois,
        montants: Object.fromEntries(elements.map((e) => [e.cle, montantDe(e)])),
        mode,
        reference: mode === 'especes' ? null : reference,
        date_paiement: date,
      })
      setRecu(data)
      setInscription({ ...inscription, situation: data.situation })
      viderSelection()
      setReference('')
    } catch (err) {
      const { message, champs } = lireErreur(err)
      setErreurs(champs)
      setMessage(message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <EnTete titre="Encaisser un paiement" sousTitre="Frais de l'année (assurance, FRAM, t-shirt…) et écolages mensuels." />
      <Alerte onFermer={() => setMessage('')}>{message}</Alerte>

      {!inscription && (
        <div className="carte">
          <h3 className="carte-titre">Rechercher un élève inscrit cette année</h3>
          <RechercheEleve
            source="inscriptions"
            params={{ presence: 'tous' }}
            onChoisir={(i) => setParams({ inscription: i.id })}
            rendreDetail={(i) => (i.date_sortie ? `${i.classe.nom} · parti(e)` : i.classe.nom)}
          />
        </div>
      )}

      {inscription && (
        <div className="paiement-layout">
          <div className="carte">
            <div className="fiche-resume ligne">
              <div>
                <strong>{nomComplet(inscription.etudiant)}</strong>{' '}
                <span className="mono muted">{inscription.etudiant.matricule}</span>
                <div className="muted">
                  {inscription.classe.nom} · {inscription.annee_scolaire.libelle}
                </div>
              </div>
              <button className="btn btn-ghost" onClick={() => setParams({})}>
                Changer d'élève
              </button>
            </div>

            <h3 className="carte-titre">Cliquez sur les éléments à payer</h3>
            <Situation
              situation={s}
              selection={mois}
              onBasculer={basculer}
              fraisSelection={fraisChoisis}
              onBasculerFrais={(cle) => setFraisChoisis((l) => (l.includes(cle) ? l.filter((x) => x !== cle) : [...l, cle]))}
            />
            <div className="raccourcis">
              <span className="muted">Raccourcis :</span>
              <button type="button" className="btn btn-petit" onClick={() => prochainsMois(1)}>
                Prochain mois
              </button>
              <button type="button" className="btn btn-petit" onClick={() => prochainsMois(3)}>
                3 mois
              </button>
              <button type="button" className="btn btn-petit" onClick={() => prochainsMois(100)}>
                Tout le reste
              </button>
              {s.frais.some((f) => f.payable && f.obligatoire) && (
                <button
                  type="button"
                  className="btn btn-petit"
                  onClick={() => setFraisChoisis(s.frais.filter((f) => f.payable && f.obligatoire).map((f) => f.cle))}
                >
                  Frais obligatoires
                </button>
              )}
              {(mois.length > 0 || fraisChoisis.length > 0) && (
                <button
                  type="button"
                  className="btn btn-petit btn-ghost"
                  onClick={viderSelection}
                >
                  Effacer
                </button>
              )}
            </div>
            {(erreurs.mois || erreurs.frais || erreurs.droit) && (
              <p className="champ-erreur">{erreurs.mois || erreurs.frais || erreurs.droit}</p>
            )}
          </div>

          <form className="carte caisse" onSubmit={soumettre}>
            <h3 className="carte-titre">Règlement</h3>
            <ul className="caisse-lignes">
              {elements.map((e) => {
                const montant = montantDe(e)
                const err = erreurMontant(e) || erreurs.montants
                return (
                  <li key={e.cle} className="ligne-versement">
                    <span>{e.libelle}</span>
                    <input
                      type="number"
                      min="1"
                      max={e.reste}
                      step="1"
                      inputMode="numeric"
                      value={montants[e.cle] ?? ''}
                      placeholder={String(e.reste)}
                      onChange={(ev) => setMontants({ ...montants, [e.cle]: ev.target.value })}
                      aria-label={`Montant versé pour ${e.libelle}`}
                    />
                    <small className={erreurMontant(e) ? 'danger' : 'muted'}>
                      {erreurMontant(e)
                        ? err
                        : montant < e.reste
                          ? `Versement partiel — il restera ${ar(e.reste - montant)} sur ${ar(e.montant_du)}`
                          : e.montant_paye > 0
                            ? `Solde (déjà versé ${ar(e.montant_paye)} sur ${ar(e.montant_du)})`
                            : `Paiement complet`}
                    </small>
                  </li>
                )
              })}
              {elements.length === 0 && <li className="muted">Aucun élément sélectionné.</li>}
            </ul>
            {elements.length > 0 && (
              <p className="muted petit-aide">
                Le parent peut payer en plusieurs fois : saisissez le montant versé (vide = tout le reste).
              </p>
            )}
            <div className="caisse-total">
              <span>Total</span>
              <strong>{ar(total)}</strong>
            </div>

            <Champ label="Mode de paiement" requis erreur={erreurs.mode}>
              <select value={mode} onChange={(e) => setMode(e.target.value)}>
                {Object.entries(MODES).map(([k, v]) => (
                  <option key={k} value={k}>
                    {v}
                  </option>
                ))}
              </select>
            </Champ>
            {mode !== 'especes' && (
              <Champ label="Référence (n° transaction / chèque)" requis erreur={erreurs.reference}>
                <input value={reference} onChange={(e) => setReference(e.target.value)} />
              </Champ>
            )}
            <Champ label="Date du paiement" requis erreur={erreurs.date_paiement}>
              <input type="date" value={date} max={aujourdhui()} onChange={(e) => setDate(e.target.value)} />
            </Champ>

            <button className="btn btn-primaire btn-bloc" disabled={envoi || total === 0 || saisieInvalide}>
              {envoi ? 'Enregistrement…' : `Encaisser ${ar(total)}`}
            </button>
          </form>
        </div>
      )}

      {recu && (
        <ModaleRecu
          recu={recu}
          onFermer={() => setRecu(null)}
          onImprimer={setPdf}
          onAnnule={(r) => {
            setRecu(r)
            setInscription((i) => ({ ...i, situation: r.situation }))
          }}
        />
      )}

      {pdf && <ApercuPdf numero={pdf} onFermer={fermerPdf} />}
    </>
  )
}
