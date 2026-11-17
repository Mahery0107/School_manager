import { useEffect, useState } from 'react'
import { api } from './api'

/** Charge les classes et années scolaires (référentiels communs à plusieurs pages). */
export function useReferentiels() {
  const [classes, setClasses] = useState([])
  const [annees, setAnnees] = useState([])
  const [chargement, setChargement] = useState(true)

  const recharger = () =>
    Promise.all([api.get('/classes'), api.get('/annees-scolaires')])
      .then(([c, a]) => {
        setClasses(c.data)
        setAnnees(a.data)
      })
      .finally(() => setChargement(false))

  useEffect(() => {
    recharger()
  }, [])

  const anneeActive = annees.find((a) => a.active) || null
  return { classes, annees, anneeActive, chargement, recharger }
}

/** Valeur "debounced" pour les champs de recherche. */
export function useDebounce(valeur, delai = 300) {
  const [v, setV] = useState(valeur)
  useEffect(() => {
    const t = setTimeout(() => setV(valeur), delai)
    return () => clearTimeout(t)
  }, [valeur, delai])
  return v
}
