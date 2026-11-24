import { useEffect, useRef, useState } from 'react'
import { api, lireErreur } from '../api'

/**
 * Affiche un PDF généré par Laravel dans une fenêtre d'aperçu, puis permet de l'imprimer (ou de le télécharger).
 * - reçu : numero="REC-…"
 * - autre document : chemin="/fournitures/pdf", params={…}, titre="…", fichier="….pdf"
 */
export default function ApercuPdf({ numero, chemin, params, titre, fichier, onFermer }) {
  const source = chemin || `/recus/${numero}/pdf`
  const cleParams = JSON.stringify(params || {})
  const nomFichier = fichier || `recu-${numero}.pdf`
  const titreAffiche = titre || (
    <>
      Reçu <span className="mono">{numero}</span>
    </>
  )
  const [url, setUrl] = useState(null)
  const [erreur, setErreur] = useState('')
  const cadre = useRef(null)

  useEffect(() => {
    let lien = null
    let actif = true
    api
      .get(source, { params: JSON.parse(cleParams), responseType: 'blob' })
      .then((r) => {
        lien = URL.createObjectURL(new Blob([r.data], { type: 'application/pdf' }))
        if (actif) setUrl(lien)
      })
      .catch((e) => actif && setErreur(lireErreur(e).message))
    return () => {
      actif = false
      if (lien) URL.revokeObjectURL(lien)
    }
  }, [source, cleParams])

  // Fermeture avec la touche Échap
  useEffect(() => {
    const touche = (e) => e.key === 'Escape' && onFermer()
    window.addEventListener('keydown', touche)
    return () => window.removeEventListener('keydown', touche)
  }, [onFermer])

  const imprimer = () => {
    try {
      cadre.current.contentWindow.focus()
      cadre.current.contentWindow.print()
    } catch {
      // Certains navigateurs bloquent l'impression d'un PDF intégré : on l'ouvre dans un onglet.
      window.open(url, '_blank')
    }
  }

  return (
    <div className="modale-fond" onClick={onFermer}>
      <div className="modale modale-pdf" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Aperçu PDF">
        <div className="modale-pdf-entete">
          <strong>{titreAffiche}</strong>
          <div className="page-actions">
            <button className="btn btn-ghost" onClick={onFermer}>
              Fermer
            </button>
            {url && (
              <a className="btn" href={url} download={nomFichier}>
                Télécharger
              </a>
            )}
            <button className="btn btn-primaire" onClick={imprimer} disabled={!url}>
              Imprimer
            </button>
          </div>
        </div>
        <div className="modale-pdf-corps">
          {erreur && <p className="danger">{erreur}</p>}
          {!url && !erreur && <p className="muted">Génération du PDF…</p>}
          {url && <iframe ref={cadre} src={url} title={typeof titre === 'string' ? titre : `Reçu ${numero}`} />}
        </div>
      </div>
    </div>
  )
}
