import { Link } from 'react-router-dom'
import { useAuth } from '../auth'
import Brand from '../components/Brand'

/**
 * Responsable du traitement : à renseigner avant toute mise en ligne
 * (établissement ou personne qui publie QuizLab, et un moyen de le contacter).
 */
const CONTROLLER = {
  name: '[à compléter : nom de l’établissement ou de l’éditeur]',
  contact: '[à compléter : adresse e-mail de contact]',
  host: '[à compléter : hébergeur et pays d’hébergement]',
  aiTransfer: '[à compléter : garanties du transfert vers les États-Unis (clauses contractuelles types ou Data Privacy Framework)]',
}

/** Durée de conservation du journal de sécurité (recommandation CNIL : 6 mois à 1 an). */
const SECURITY_LOG_RETENTION = '6 mois'

/** Page de politique de confidentialité. */
export default function Privacy() {
  const { user } = useAuth()

  return (
    <div className="page prose">
      <header className="page-head">
        <div>
          <Link to={user ? '/' : '/login'} className="brand" style={{ marginBottom: '1rem' }}>
            <Brand />
          </Link>
          <h1>Confidentialité et données personnelles</h1>
          <p>Version du 16 septembre 2026 — ce que QuizLab collecte, pourquoi, combien de temps, et tes droits.</p>
        </div>
      </header>

      <h2>En bref</h2>
      <ul>
        <li>On ne collecte que ce qui sert à faire fonctionner les quiz : un e-mail, un pseudo, un mot de passe, tes parties.</li>
        <li>
          Pas de publicité, pas de mesure d’audience, pas de cookie de pistage. Polices et images sont servies par
          QuizLab lui-même.
        </li>
        <li>
          Un seul service extérieur : la génération de quiz par IA, réservée aux professeurs, qui ne reçoit que le
          sujet demandé — jamais ton nom, ton e-mail ni tes résultats.
        </li>
        <li>Tu peux télécharger toutes tes données et supprimer ton compte à tout moment, depuis « Mon compte ».</li>
      </ul>

      <h2>Qui est responsable ?</h2>
      <p>
        {CONTROLLER.name}. Contact pour toute question sur tes données : {CONTROLLER.contact}.
      </p>

      <h2>Quelles données, et pourquoi ?</h2>
      <div className="card" style={{ overflowX: 'auto' }}>
        <table>
          <thead>
            <tr>
              <th>Données</th>
              <th>À quoi elles servent</th>
              <th>Base légale</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>E-mail, mot de passe (stocké haché, jamais en clair)</td>
              <td>Te connecter à ton compte</td>
              <td>Exécution du service (art. 6-1-b RGPD)</td>
            </tr>
            <tr>
              <td>Pseudo, rôle (joueur, professeur, administrateur)</td>
              <td>T’afficher dans les classements, signer les quiz que tu rédiges, gérer les droits</td>
              <td>Exécution du service</td>
            </tr>
            <tr>
              <td>Parties jouées : réponses, score, durée ; en direct : temps de réponse et points</td>
              <td>Corriger, classer, te montrer ton historique</td>
              <td>Exécution du service</td>
            </tr>
            <tr>
              <td>Quiz et images que tu publies</td>
              <td>Les proposer aux autres joueurs</td>
              <td>Exécution du service</td>
            </tr>
            <tr>
              <td>
                Journal de sécurité : adresse IP, identifiant du compte, action demandée et son résultat (jamais de mot
                de passe, de jeton ni d’e-mail)
              </td>
              <td>Repérer les abus (mots de passe essayés en boucle, codes PIN balayés) et enquêter sur un incident</td>
              <td>Intérêt légitime : sécuriser le service (art. 6-1-f et 32 RGPD)</td>
            </tr>
            <tr>
              <td>Dates d’inscription, de dernière connexion et d’acceptation de cette politique</td>
              <td>Appliquer les durées de conservation, prouver ton accord</td>
              <td>Obligation de rendre compte (art. 5-2 et 7-1 RGPD)</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2>Qui les voit ?</h2>
      <ul>
        <li>Les autres joueurs voient ton pseudo et tes scores dans les classements et les parties en direct.</li>
        <li>Les administrateurs voient ton pseudo, ton rôle et tes statistiques de jeu — pas ton adresse e-mail.</li>
        <li>
          Génération de quiz par IA : le sujet saisi par le professeur, le nombre de questions et la difficulté sont
          envoyés à Groq, Inc. (États-Unis), qui rédige le quiz. Aucune donnée d’élève ni de compte n’est transmise.
          Garanties du transfert : {CONTROLLER.aiTransfer}. Professeurs : n’écrivez pas de donnée personnelle dans le
          sujet.
        </li>
        <li>
          Personne d’autre : aucune donnée n’est vendue ni cédée. Hébergement : {CONTROLLER.host}.
        </li>
      </ul>

      <h2>Combien de temps ?</h2>
      <ul>
        <li><b>Compte et historique</b> : jusqu’à ce que tu supprimes ton compte, ou après 3 ans sans connexion.</li>
        <li><b>Jeton de connexion</b> : 30 jours, ou jusqu’à la déconnexion.</li>
        <li><b>Journal de sécurité</b> : {SECURITY_LOG_RETENTION}, puis effacé.</li>
        <li><b>Parties en direct</b> : 24 heures. Ton résultat reste ensuite dans ton historique.</li>
        <li>
          <b>Quiz rédigés</b> : ils restent disponibles après la suppression du compte, mais le nom de l’auteur est
          remplacé par « compte supprimé ».
        </li>
      </ul>

      <h2>Dans ton navigateur</h2>
      <p>
        QuizLab garde ton jeton de connexion dans le stockage local du navigateur, uniquement pour te garder
        connecté. C’est strictement nécessaire au service : aucun bandeau de consentement n’est donc requis, et rien
        d’autre n’y est enregistré. La déconnexion l’efface.
      </p>

      <h2>Tes droits</h2>
      <ul>
        <li><b>Accès et portabilité</b> : « Mon compte » → « Télécharger mes données » (fichier JSON).</li>
        <li><b>Effacement</b> : « Mon compte » → « Supprimer mon compte ». C’est immédiat et définitif.</li>
        <li><b>Rectification, opposition, limitation</b> : écris à {CONTROLLER.contact}.</li>
        <li>
          Si tu estimes que tes droits ne sont pas respectés, tu peux adresser une réclamation à la CNIL (cnil.fr).
        </li>
      </ul>

      <h2>Mineurs et images</h2>
      <ul>
        <li>Si tu as moins de 15 ans, demande l’accord d’un parent avant de créer un compte.</li>
        <li>
          Professeurs : n’ajoutez pas aux quiz de photos où l’on reconnaît une personne sans son autorisation. Les
          images sont hébergées par QuizLab lui-même, jamais chargées depuis un site extérieur.
        </li>
      </ul>

      <div className="row" style={{ marginTop: '2rem' }}>
        <Link className="btn primary" to={user ? '/' : '/login'}>
          {user ? 'Retour à QuizLab' : 'Retour à la connexion'}
        </Link>
        {user && (
          <Link className="btn ghost" to="/account">
            Mon compte
          </Link>
        )}
      </div>
    </div>
  )
}
