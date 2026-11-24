import { useEffect, useState } from 'react'

/**
 * Mode clair / sombre.
 * Le choix est gardé sur cet ordinateur (localStorage) ; sans choix, on suit le réglage de Windows / du navigateur.
 * Le thème est posé sur <html data-theme="…"> (voir aussi le petit script dans index.html, qui l'applique
 * avant l'affichage pour éviter un flash blanc).
 */
const CLE = 'gestion-ecole-theme'

export function themeInitial() {
  try {
    const choix = localStorage.getItem(CLE)
    if (choix === 'clair' || choix === 'sombre') return choix
  } catch {
    /* stockage indisponible */
  }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'sombre' : 'clair'
}

export function appliquerTheme(theme) {
  document.documentElement.dataset.theme = theme === 'sombre' ? 'dark' : 'light'
}

export function useTheme() {
  const [theme, setTheme] = useState(themeInitial)

  useEffect(() => {
    appliquerTheme(theme)
  }, [theme])

  const basculer = () =>
    setTheme((t) => {
      const suivant = t === 'sombre' ? 'clair' : 'sombre'
      try {
        localStorage.setItem(CLE, suivant)
      } catch {
        /* le thème reste valable jusqu'au rechargement */
      }
      return suivant
    })

  return [theme, basculer]
}
