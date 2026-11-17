import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})

/* ---------- Session du compte administrateur (jeton Sanctum) ---------- */
const CLE_JETON = 'gestion-ecole-jeton'

const lireStockage = () => {
  try {
    return localStorage.getItem(CLE_JETON)
  } catch {
    return null
  }
}

export const session = {
  jeton: lireStockage(),
  ouvrir(jeton) {
    this.jeton = jeton
    try {
      localStorage.setItem(CLE_JETON, jeton)
    } catch {
      /* stockage indisponible : session valable jusqu'au rechargement */
    }
  },
  fermer() {
    this.jeton = null
    try {
      localStorage.removeItem(CLE_JETON)
    } catch {
      /* rien */
    }
  },
}

api.interceptors.request.use((config) => {
  if (session.jeton) config.headers.Authorization = `Bearer ${session.jeton}`
  return config
})

// Jeton expiré ou invalide : retour à l'écran de connexion
api.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err?.response?.status === 401 && session.jeton) {
      session.fermer()
      window.dispatchEvent(new Event('session-expiree'))
    }
    return Promise.reject(err)
  },
)

/** Extrait un message lisible + les erreurs par champ d'une erreur Laravel. */
export function lireErreur(err) {
  const data = err?.response?.data
  if (!err?.response) return { message: 'Serveur injoignable. Le backend Laravel est-il démarré ?', champs: {} }
  const champs = {}
  for (const [k, v] of Object.entries(data?.errors || {})) champs[k.split('.')[0]] = v[0]
  return { message: data?.message || 'Une erreur est survenue.', champs }
}

const fmtArgent = new Intl.NumberFormat('fr-FR')
export const ar = (n) => `${fmtArgent.format(n || 0)} Ar`

export const nomMois = (ym) => {
  if (!ym) return ''
  const [a, m] = ym.split('-').map(Number)
  const s = new Date(a, m - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
  return s.charAt(0).toUpperCase() + s.slice(1)
}

export const moisCourt = (ym) => {
  const [a, m] = ym.split('-').map(Number)
  return new Date(a, m - 1, 1).toLocaleDateString('fr-FR', { month: 'short' }).replace('.', '')
}

export const dateFr = (d) => (d ? new Date(d).toLocaleDateString('fr-FR') : '')

export const nomComplet = (e) => (e ? `${e.nom} ${e.prenom || ''}`.trim() : '')

export const MODES = {
  especes: 'Espèces',
  mobile_money: 'Mobile Money',
  cheque: 'Chèque',
  virement: 'Virement',
}

/** Motifs de sortie d'un élève en cours d'année (identiques à Inscription::MOTIFS_SORTIE côté Laravel). */
export const MOTIFS_SORTIE = {
  renvoye: 'Renvoyé',
  abandon: "A quitté l'école",
  transfert: 'Transféré dans une autre école',
  autre: 'Autre motif',
}

// date du jour en heure locale (toISOString donnerait la veille entre minuit et 3 h à Madagascar)
export const aujourdhui = () => new Date().toLocaleDateString('sv-SE')
