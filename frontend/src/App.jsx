import { useEffect, useState } from 'react'
import { BrowserRouter, NavLink, Route, Routes, useLocation } from 'react-router-dom'
import { api } from './api'
import Eleves from './pages/Eleves'
import FicheEleve from './pages/FicheEleve'
import Inscription from './pages/Inscription'
import Journal from './pages/Journal'
import Paiement from './pages/Paiement'
import Parametres from './pages/Parametres'
import Reinscription from './pages/Reinscription'
import Tableau from './pages/Tableau'

const MENU = [
  { to: '/', label: 'Tableau de bord', fin: true },
  { titre: 'Scolarité' },
  { to: '/inscription', label: 'Nouvelle inscription' },
  { to: '/reinscription', label: 'Réinscription' },
  { to: '/eleves', label: 'Élèves inscrits' },
  { titre: 'Caisse' },
  { to: '/paiements/nouveau', label: 'Encaisser un paiement' },
  { to: '/paiements', label: 'Journal des paiements', fin: true },
  { titre: 'Administration' },
  { to: '/parametres', label: 'Paramètres' },
]

function Layout() {
  const [menuOuvert, setMenuOuvert] = useState(false)
  const location = useLocation()
  useEffect(() => setMenuOuvert(false), [location.pathname])

  // Nom de l'établissement, défini côté Laravel (APP_ECOLE dans backend/.env)
  const [ecole, setEcole] = useState('')
  useEffect(() => {
    api
      .get('/etablissement')
      .then((r) => {
        setEcole(r.data.nom)
        document.title = r.data.nom
      })
      .catch(() => {})
  }, [])

  return (
    <div className="app">
      <aside className={`sidebar ${menuOuvert ? 'ouverte' : ''}`}>
        <div className="logo">
          <span className="logo-marque">É</span>
          <span>
            {ecole || 'Gestion École'}
            <small>Scolarité & caisse</small>
          </span>
        </div>
        <nav>
          {MENU.map((m, i) =>
            m.titre ? (
              <div className="nav-titre" key={i}>
                {m.titre}
              </div>
            ) : (
              <NavLink key={m.to} to={m.to} end={m.fin} className="nav-lien">
                {m.label}
              </NavLink>
            ),
          )}
        </nav>
      </aside>

      <div className="principal">
        <div className="barre-mobile">
          <button className="btn btn-ghost" onClick={() => setMenuOuvert(!menuOuvert)} aria-label="Menu">
            ☰
          </button>
          <strong>{ecole || 'Gestion École'}</strong>
        </div>
        <main className="contenu">
          <Routes>
            <Route path="/" element={<Tableau />} />
            <Route path="/inscription" element={<Inscription />} />
            <Route path="/reinscription" element={<Reinscription />} />
            <Route path="/eleves" element={<Eleves />} />
            <Route path="/eleves/:id" element={<FicheEleve />} />
            <Route path="/paiements/nouveau" element={<Paiement />} />
            <Route path="/paiements" element={<Journal />} />
            <Route path="/parametres" element={<Parametres />} />
            <Route path="*" element={<p>Page introuvable.</p>} />
          </Routes>
        </main>
      </div>
      {menuOuvert && <div className="voile" onClick={() => setMenuOuvert(false)} />}
    </div>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <Layout />
    </BrowserRouter>
  )
}
