import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})

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

export const aujourdhui = () => new Date().toISOString().slice(0, 10)
