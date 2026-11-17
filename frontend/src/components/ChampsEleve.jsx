import { Champ } from './ui'

export const ELEVE_VIDE = {
  nom: '', prenom: '', sexe: '', date_naissance: '', lieu_naissance: '', adresse: '',
  nom_pere: '', profession_pere: '', telephone_pere: '',
  nom_mere: '', profession_mere: '', telephone_mere: '',
}

/** Champs d'identité de l'élève + informations du père et de la mère. */
export default function ChampsEleve({ form, maj, erreurs }) {
  const val = (k) => form[k] ?? ''

  return (
    <>
      <fieldset>
        <legend>Identité de l'élève</legend>
        <div className="grille-champs">
          <Champ label="Nom" requis erreur={erreurs.nom}>
            <input value={val('nom')} onChange={maj('nom')} autoFocus />
          </Champ>
          <Champ label="Prénom(s)" erreur={erreurs.prenom}>
            <input value={val('prenom')} onChange={maj('prenom')} />
          </Champ>
          <Champ label="Sexe" requis erreur={erreurs.sexe}>
            <select value={val('sexe')} onChange={maj('sexe')}>
              <option value="">—</option>
              <option value="M">Masculin</option>
              <option value="F">Féminin</option>
            </select>
          </Champ>
          <Champ label="Date de naissance" requis erreur={erreurs.date_naissance}>
            <input type="date" value={val('date_naissance')} onChange={maj('date_naissance')} />
          </Champ>
          <Champ label="Lieu de naissance" erreur={erreurs.lieu_naissance}>
            <input value={val('lieu_naissance')} onChange={maj('lieu_naissance')} />
          </Champ>
          <Champ label="Adresse" erreur={erreurs.adresse}>
            <input value={val('adresse')} onChange={maj('adresse')} />
          </Champ>
        </div>
      </fieldset>

      <fieldset>
        <legend>Parents</legend>
        <p className="aide">Renseignez au moins le père ou la mère, avec un numéro de téléphone.</p>
        <div className="parents">
          {[
            ['pere', 'Père'],
            ['mere', 'Mère'],
          ].map(([k, titre]) => (
            <div className="parent-bloc" key={k}>
              <h4>{titre}</h4>
              <Champ label="Nom et prénom(s)" erreur={erreurs[`nom_${k}`]}>
                <input value={val(`nom_${k}`)} onChange={maj(`nom_${k}`)} />
              </Champ>
              <Champ label="Profession" erreur={erreurs[`profession_${k}`]}>
                <input value={val(`profession_${k}`)} onChange={maj(`profession_${k}`)} />
              </Champ>
              <Champ label="Téléphone" erreur={erreurs[`telephone_${k}`]}>
                <input type="tel" value={val(`telephone_${k}`)} onChange={maj(`telephone_${k}`)} />
              </Champ>
            </div>
          ))}
        </div>
      </fieldset>
    </>
  )
}

/** Résumé court des parents : « Père : X (034…) · Mère : Y (033…) ». */
export function resumeParents(e) {
  return [
    e.nom_pere && `Père : ${e.nom_pere}${e.telephone_pere ? ` (${e.telephone_pere})` : ''}`,
    e.nom_mere && `Mère : ${e.nom_mere}${e.telephone_mere ? ` (${e.telephone_mere})` : ''}`,
  ]
    .filter(Boolean)
    .join(' · ')
}
