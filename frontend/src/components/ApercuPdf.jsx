import { useEffect, useRef, useState } from 'react'
import { api, lireErreur } from '../api'

/**
 * Affiche le reçu PDF généré par Laravel dans une fenêtre d'aperçu,
 * puis permet de l'imprimer (ou de le télécharger).
 */
export default function ApercuPdf({ numero, onFermer }) {
  const [url, setUrl] = useState(null)
  const [erreur, setErreur] = useState('')
  const cadre = useRef(null)

  useEffect(() => {
    let lien = null
    let actif = true
    api
      .get(`/recus/${numero}/pdf`, { responseType: 'blob' })
      .then((r) => {
        lien = URL.createObjectURL(new Blob([r.data], { type: 'application/pdf' }))
        if (actif) setUrl(lien)
      })
      .catch((e) => actif && setErreur(lireErreur(e).message))
    return () => {
      actif = false
      if (lien) URL.revokeObjectURL(lien)
    }
  }, [numero])

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
      <div className="modale modale-pdf" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Aperçu du reçu PDF">
        <div className="modale-pdf-entete">
          <strong>
            Reçu <span className="mono">{numero}</span>
          </strong>
          <div className="page-actions">
            <button className="btn btn-ghost" onClick={onFermer}>
              Fermer
            </button>
            {url && (
              <a className="btn" href={url} download={`recu-${numero}.pdf`}>
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
          {url && <iframe ref={cadre} src={url} title={`Reçu ${numero}`} />}
        </div>
      </div>
    </div>
  )
}
