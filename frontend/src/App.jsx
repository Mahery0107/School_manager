import { useEffect, useState } from 'react'
import { BrowserRouter, NavLink, Route, Routes, useLocation } from 'react-router-dom'
import { api, session } from './api'
import Connexion from './pages/Connexion'
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

function Layout({ ecole, utilisateur, setUtilisateur, onDeconnexion }) {
  const [menuOuvert, setMenuOuvert] = useState(false)
  const location = useLocation()
  useEffect(() => setMenuOuvert(false), [location.pathname])

  return (
    <div className="app">
      <aside className={`sidebar ${menuOuvert ? 'ouverte' : ''}`}>
        <div className="logo">
          <img className="logo-image" src="/logo.svg" alt="Logo de l'école" />
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
        <div className="sidebar-compte">
          <span className="compte-avatar">{(utilisateur.name || 'A').charAt(0).toUpperCase()}</span>
          <span className="compte-nom">
            {utilisateur.name}
            <small>{utilisateur.identifiant}</small>
          </span>
          <button className="btn-deconnexion" onClick={onDeconnexion} title="Se déconnecter">
            Déconnexion
          </button>
        </div>
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
            <Route
              path="/parametres"
              element={<Parametres utilisateur={utilisateur} onCompteModifie={setUtilisateur} />}
            />
            <Route path="*" element={<p>Page introuvable.</p>} />
          </Routes>
        </main>
      </div>
      {menuOuvert && <div className="voile" onClick={() => setMenuOuvert(false)} />}
    </div>
  )
}

export default function App() {
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

  // undefined : vérification en cours · null : non connecté · objet : administrateur connecté
  const [utilisateur, setUtilisateur] = useState(session.jeton ? undefined : null)
  const [messageConnexion, setMessageConnexion] = useState('')

  useEffect(() => {
    if (!session.jeton) return
    api
      .get('/moi')
      .then((r) => setUtilisateur(r.data))
      .catch(() => setUtilisateur(null))
  }, [])

  useEffect(() => {
    const expiree = () => {
      setMessageConnexion('Votre session a expiré. Veuillez vous reconnecter.')
      setUtilisateur(null)
    }
    window.addEventListener('session-expiree', expiree)
    return () => window.removeEventListener('session-expiree', expiree)
  }, [])

  const deconnecter = async () => {
    try {
      await api.post('/logout')
    } catch {
      /* jeton déjà invalide : on ferme quand même la session locale */
    }
    session.fermer()
    setMessageConnexion('')
    setUtilisateur(null)
  }

  if (utilisateur === undefined) return <div className="chargement-plein">Chargement…</div>

  if (!utilisateur)
    return (
      <Connexion
        ecole={ecole}
        message={messageConnexion}
        onConnecte={(u) => {
          setMessageConnexion('')
          setUtilisateur(u)
        }}
      />
    )

  return (
    <BrowserRouter>
      <Layout ecole={ecole} utilisateur={utilisateur} setUtilisateur={setUtilisateur} onDeconnexion={deconnecter} />
    </BrowserRouter>
  )
}
