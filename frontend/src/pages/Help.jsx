import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth'
import { Icon } from '../components/Icon'
import adminKeys from '../assets/aide/admin-cles.webp'
import account from '../assets/aide/compte.webp'
import aiChat from '../assets/aide/chat-ia.webp'
import correction from '../assets/aide/correction.webp'
import editor from '../assets/aide/editeur.webp'
import hostLobby from '../assets/aide/direct-salle-attente.webp'
import hostReveal from '../assets/aide/direct-animateur-correction.webp'
import library from '../assets/aide/bibliotheque.webp'
import playerAnswer from '../assets/aide/direct-joueur-correction.webp'
import playerQuestion from '../assets/aide/direct-joueur-question.webp'
import progress from '../assets/aide/parcours.webp'
import soloPlay from '../assets/aide/partie-solo.webp'

/**
 * Guides d'utilisation, par public. Chaque guide : quelques étapes et les captures de l'écran
 * concerné. Le texte suit les libellés réels de l'interface : à mettre à jour s'ils changent.
 */
const GUIDES = {
  eleve: {
    label: 'Élève',
    icon: 'book-open',
    sections: [
      {
        title: 'Jouer un quiz seul',
        steps: [
          <>Sur l’<Link to="/">accueil</Link>, choisis une matière ou cherche un thème, puis clique sur <b>Jouer</b>.</>,
          <>Réponds question par question : tu peux revenir en arrière avec <b>Précédent</b> et changer d’avis.</>,
          <>Clique sur <b>Terminer le quiz</b> : la correction s’affiche tout de suite.</>,
        ],
        shots: [{ src: library, alt: 'Accueil : bibliothèque de quiz, progression et classement' }, { src: soloPlay, alt: 'Une question en cours, réponse sélectionnée' }],
      },
      {
        title: 'Comprendre ses erreurs',
        steps: [
          <>Le bilan donne ton score, ton temps et ta place dans la classe.</>,
          <>La partie <b>À retenir</b> reprend chaque question ratée : ta réponse, la bonne, et une explication.</>,
          <>Seule ta <b>première</b> partie compte dans le classement du quiz : rejouer sert à progresser, pas à grimper.</>,
        ],
        shots: [{ src: correction, alt: 'Bilan d’une partie avec les questions à retenir' }],
      },
      {
        title: 'Rejoindre une partie en classe',
        steps: [
          <>Ton professeur affiche un <b>code PIN</b> à 6 chiffres au tableau.</>,
          <>Saisis-le sur l’accueil ou la page <Link to="/join">Rejoindre</Link>, puis <b>Rejoindre la partie</b> : ton pseudo apparaît au tableau.</>,
          <>Chaque réponse a une <b>couleur et une forme</b> (▲ ◆ ● ■), les mêmes au tableau et sur ton téléphone : touche la tuile de la réponse que tu choisis. Plus tu es rapide, plus tu gagnes de points (de 1000 à 500).</>,
        ],
        shots: [{ src: playerQuestion, alt: 'Téléphone : tuiles de réponse', mobile: true }, { src: playerAnswer, alt: 'Téléphone : bonne réponse et points gagnés', mobile: true }],
      },
      {
        title: 'Suivre ses progrès',
        steps: [
          <>La page <Link to="/history">Mon parcours</Link> montre ta maîtrise par matière et les quiz <b>à revoir</b>.</>,
          <>Un quiz est <b>maîtrisé</b> dès 80 % de réussite ; il est <b>à revoir</b> si ta dernière partie est sous 50 %.</>,
          <>Les parties en classe y apparaissent aussi, avec leur correction.</>,
        ],
        shots: [{ src: progress, alt: 'Mon parcours : maîtrise par matière et quiz à retravailler' }],
      },
    ],
  },
  prof: {
    label: 'Professeur',
    icon: 'pen',
    sections: [
      {
        title: 'Devenir professeur',
        steps: [
          <>Demande une <b>clé d’accès professeur</b> à l’administrateur de ton établissement.</>,
          <>Saisis-la dans <Link to="/account">Mon compte</Link> → <b>Clé d’accès</b> → <b>Valider la clé</b>. Le menu <b>Créer</b> apparaît.</>,
        ],
        shots: [{ src: account, alt: 'Mon compte : clé d’accès, clé IA et données personnelles' }],
      },
      {
        title: 'Créer un quiz',
        steps: [
          <>Menu <b>Créer</b> : donne un titre, une matière, une difficulté et, si tu veux, une image de couverture.</>,
          <>Pour chaque question : l’intitulé, 2 à 6 réponses, <b>coche la bonne</b>, règle le chrono (utile en classe) et ajoute une explication — elle s’affiche dans la correction.</>,
          <>Clique sur <b>Publier le quiz</b>. Tu pourras le modifier plus tard avec <b>Modifier</b> depuis sa carte.</>,
        ],
        shots: [{ src: editor, alt: 'Éditeur de quiz' }],
      },
      {
        title: 'Générer un quiz avec l’IA',
        steps: [
          <>Il faut une <b>clé IA</b> (nombre de générations limité), à saisir dans <Link to="/account">Mon compte</Link> → <b>Clé IA</b>.</>,
          <>Sur la page <b>Créer</b>, ouvre la bulle <b>Créer un quiz</b> en bas à droite, choisis le nombre de questions, de réponses et la difficulté.</>,
          <>Décris le sujet en une phrase : le quiz est rédigé et publié. <b>Relis-le</b> avec <b>Modifier</b> avant de le jouer en classe : l’IA peut se tromper.</>,
        ],
        shots: [{ src: aiChat, alt: 'Bulle de génération de quiz par IA' }],
      },
      {
        title: 'Animer une partie en classe',
        steps: [
          <>Sur la carte d’un quiz, clique sur <b>Lancer en classe</b> et projette l’écran : le code PIN s’affiche.</>,
          <>Quand les pseudos des élèves sont apparus, clique sur <b>Lancer la partie</b>.</>,
          <>La question se clôt à la fin du chrono ou dès que tout le monde a répondu (sinon <b>Afficher la réponse</b>). Commente la répartition, puis <b>Question suivante</b>… jusqu’au <b>podium</b>.</>,
          <><b>Arrêter la partie</b> la termine à tout moment : les questions déjà posées restent dans l’historique des élèves.</>,
        ],
        shots: [{ src: hostLobby, alt: 'Salle d’attente : le PIN à projeter et les élèves connectés' }, { src: hostReveal, alt: 'Correction en direct : répartition des réponses et classement' }],
      },
    ],
  },
  admin: {
    label: 'Administrateur',
    icon: 'key',
    sections: [
      {
        title: 'Donner un rôle professeur ou administrateur',
        steps: [
          <>Menu <b>Administration</b> → <b>Clés d’accès</b>.</>,
          <>Pour une personne déjà inscrite : cherche son compte dans <b>Attribuer à</b>, le rôle est donné tout de suite.</>,
          <>Sinon, laisse le champ vide : un <b>code</b> est généré, à transmettre. Une clé professeur peut servir à toute une équipe ; une clé administrateur ne sert qu’une fois.</>,
          <>Pour retirer un rôle, passe par <b>Comptes</b> : révoquer une clé n’enlève pas les droits déjà accordés.</>,
        ],
        shots: [{ src: adminKeys, alt: 'Administration : clés d’accès' }],
      },
      {
        title: 'Distribuer des clés IA',
        steps: [
          <><b>Administration</b> → <b>Clés IA</b> : choisis le nombre de générations et, si besoin, une date d’expiration.</>,
          <>Attribue-la directement à un professeur, ou transmets le code qu’il saisira dans <b>Mon compte</b>.</>,
        ],
        shots: [],
      },
    ],
  },
}

/** Onglet ouvert par défaut : le guide qui correspond au rôle du compte. */
const DEFAULT_TAB = { user: 'eleve', prof: 'prof', admin: 'admin' }

/** Page d'aide : guides d'utilisation illustrés, par public. */
export default function Help() {
  const { role, canCreate, isAdmin } = useAuth()
  const [tab, setTab] = useState(DEFAULT_TAB[role] ?? 'eleve')
  const guide = GUIDES[tab]

  return (
    <div className="page help">
      <header className="page-head">
        <div>
          <span className="eyebrow"><Icon name="lightbulb" size={14} /> Aide</span>
          <h1 style={{ marginTop: '0.4rem' }}>Bien démarrer avec QuizLab</h1>
          <p>Les gestes essentiels, écran par écran.</p>
        </div>
      </header>

      <div className="help-tabs" role="tablist">
        {Object.entries(GUIDES).map(([key, { label, icon }]) => (
          <button key={key} type="button" role="tab" aria-selected={tab === key} className={tab === key ? 'active' : ''} onClick={() => setTab(key)}>
            <Icon name={icon} size={16} /> {label}
          </button>
        ))}
      </div>

      {tab !== 'eleve' && !(tab === 'prof' ? canCreate : isAdmin) && (
        <p className="help-hint">
          Ces fonctions demandent le rôle {guide.label.toLowerCase()} : ton compte ne les voit pas encore.
        </p>
      )}

      <div className="stack" style={{ gap: '1.5rem' }}>
        {guide.sections.map((section, index) => (
          <section key={section.title} className="card help-section">
            <h2>
              <span className="help-step">{index + 1}</span> {section.title}
            </h2>
            <ol>
              {section.steps.map((step, n) => (
                <li key={n}>{step}</li>
              ))}
            </ol>
            {section.shots.length > 0 && (
              <div className={`help-shots${section.shots.every((shot) => shot.mobile) ? ' mobile' : ''}`}>
                {section.shots.map((shot) => (
                  <figure key={shot.alt}>
                    <img src={shot.src} alt={shot.alt} loading="lazy" />
                    <figcaption>{shot.alt}</figcaption>
                  </figure>
                ))}
              </div>
            )}
          </section>
        ))}
      </div>
    </div>
  )
}
