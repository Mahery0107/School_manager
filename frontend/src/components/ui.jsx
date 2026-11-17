export function Champ({ label, erreur, requis, children, large }) {
  return (
    <label className={`champ ${large ? 'champ-large' : ''}`}>
      <span className="champ-label">
        {label} {requis && <em className="requis">*</em>}
      </span>
      {children}
      {erreur && <span className="champ-erreur">{erreur}</span>}
    </label>
  )
}

export function Alerte({ type = 'erreur', children, onFermer }) {
  if (!children) return null
  return (
    <div className={`alerte alerte-${type}`} role={type === 'erreur' ? 'alert' : 'status'}>
      <span>{children}</span>
      {onFermer && (
        <button type="button" className="alerte-fermer" onClick={onFermer} aria-label="Fermer">
          ×
        </button>
      )}
    </div>
  )
}

export function EnTete({ titre, sousTitre, children }) {
  return (
    <header className="page-entete">
      <div>
        <h1>{titre}</h1>
        {sousTitre && <p className="sous-titre">{sousTitre}</p>}
      </div>
      {children && <div className="page-actions">{children}</div>}
    </header>
  )
}

export function Badge({ ton = 'neutre', children }) {
  return <span className={`badge badge-${ton}`}>{children}</span>
}

export function Pagination({ page, setPage }) {
  if (!page || page.last_page <= 1) return null
  return (
    <div className="pagination">
      <button className="btn btn-ghost" disabled={page.current_page <= 1} onClick={() => setPage(page.current_page - 1)}>
        ← Précédent
      </button>
      <span>
        Page {page.current_page} / {page.last_page} · {page.total} résultat(s)
      </span>
      <button
        className="btn btn-ghost"
        disabled={page.current_page >= page.last_page}
        onClick={() => setPage(page.current_page + 1)}
      >
        Suivant →
      </button>
    </div>
  )
}

export function Vide({ children }) {
  return <div className="vide">{children}</div>
}
