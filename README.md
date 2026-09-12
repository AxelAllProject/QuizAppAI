# QuizLab

Site de quiz : bibliothèque de quiz illustrés, création de questionnaires personnalisés,
parties solo chronométrées avec correction détaillée, et **parties en direct façon Kahoot**
(code PIN, chrono, points selon la rapidité, podium).
Backend **Symfony 8** (API JSON + SQLite), frontend **React 19 / Vite**, thème sombre avec
la paire de polices Google Fonts **Fredoka** (titres, gros scores, tuiles de jeu) et
**Plus Jakarta Sans** (texte courant) ; confettis CSS pour célébrer les bons scores et
les podiums, sans dépendance externe. Les propositions du mode solo comme des parties en
direct partagent les mêmes tuiles colorées, pour une expérience cohérente du début à la fin.

## Démarrer

```bash
# Backend — http://localhost:8000
cd backend
composer install
php bin/console doctrine:migrations:migrate   # crée / met à jour var/quiz_dev.db
php bin/console app:seed                      # 3 quiz de démonstration
symfony server:start -d

# Frontend — http://localhost:5173
cd ../frontend
npm install
npm run dev
```

L'URL de l'API côté front se règle avec `VITE_API_URL` (défaut `http://localhost:8000`).

## Connexion

De vrais comptes : **e-mail + mot de passe** (haché par le composant Security de Symfony).
À la connexion, le serveur remet un **jeton** que le navigateur renvoie à chaque requête
(`Authorization: Bearer …`). Le rôle est relu **en base** à chaque requête : il ne peut
plus être forgé côté client, et un changement de rôle s'applique immédiatement.

Une **clé d'accès** saisie à l'inscription (ou plus tard dans **Mon compte**) donne le rôle
**professeur** ou **administrateur**. Les clés se créent depuis **Administration → Clés d'accès**,
avec un champ de recherche pour **attribuer directement** un rôle à un compte existant (le
rôle est accordé tout de suite, sans code à transmettre) — ou en ligne de commande :

```bash
cd backend
php bin/console app:access-key --list                     # clés existantes
php bin/console app:access-key prof --label="Mme Martin"  # nouvelle clé professeur
php bin/console app:access-key admin                      # nouvelle clé administrateur
```

📄 **Clés en place, rôles et gestion : [docs/administration.md](docs/administration.md)**

## Ce que l'on peut faire

| Rôle | Actions |
|---|---|
| **Joueur** | parcourir la bibliothèque, **jouer en solo**, **rejoindre une partie en direct** avec un code PIN, voir sa correction, les classements, son historique |
| **Professeur** | + **créer / modifier / supprimer ses propres quiz** (images, chrono par question), **animer une partie en direct** |
| **Admin** | + **modérer tous les quiz**, **générer et révoquer les clés**, **changer le rôle d'un compte**, tableau de bord |

Les droits sont vérifiés **côté serveur** (403), pas seulement masqués dans l'interface.
La correction est calculée côté serveur : les bonnes réponses ne sont jamais envoyées
au navigateur pendant une question.

## Créer un quiz par IA (chatbot)

Une **bulle de chat flottante** (en bas à droite, visible sur toutes les pages pour les
professeurs et administrateurs) permet de décrire un sujet en une phrase : l'**API Groq**
(gratuite) rédige les questions et le quiz est **publié directement** dans la bibliothèque,
prêt à jouer — sans étape manuelle intermédiaire. La réponse montre un aperçu des questions
et des liens pour le jouer, le modifier ou le supprimer ; rien n'empêche d'en redemander un
autre juste après, dans la même conversation.

Chaque quiz créé reste un quiz comme un autre : modifiable depuis l'éditeur manuel,
avec images et chronos à ajouter à la main si besoin.

### Fonctionnalité premium : la clé IA

Avoir le rôle **professeur** ou **administrateur** ne suffit pas : la génération demande
en plus une **clé IA**, distincte de la clé d'accès qui donne le rôle. Une clé IA :

- donne droit à un **nombre fixe de générations** (5 par défaut, réglable par l'admin) qui
  ne se renouvelle pas ;
- se lie à **un compte** — soit au premier qui saisit le code (dans **Mon compte → Clé IA**),
  soit **directement** à un compte choisi par l'admin (recherché par pseudo), sans code à
  transmettre ;
- peut avoir une **expiration** facultative (en jours) ;
- s'émet et se révoque depuis **Administration → Clés IA**, par un administrateur.

Ceci s'applique à **tout le monde, y compris les administrateurs** : le rôle ouvre la
porte, la clé donne les générations — pensé pour garder la main sur le quota gratuit
partagé, quel que soit le rôle de qui génère.

**Activer la génération** (facultatif, désactivée par défaut) :

1. Créer une clé gratuite sur https://console.groq.com/keys (compte Google/GitHub/e-mail,
   sans carte bancaire)
2. L'ajouter dans `backend/.env.local` : `GROQ_API_KEY=ta-clé`
3. Redémarrer le serveur

Sans clé configurée, le chatbot reste accessible mais répond avec une erreur explicite
invitant à contacter un administrateur — le reste du site fonctionne normalement. Le
brouillon généré passe par **la même validation** qu'un quiz saisi à la main
(`QuizWriter`) : un quiz mal formé n'est jamais publié à moitié. Pour se protéger d'un
usage excessif du quota gratuit, chaque compte est limité à **15 générations par heure**
(`ai_quiz_generation` dans `config/packages/rate_limiter.yaml`).

## Quiz personnalisés et images

Dans l'éditeur : une **image de couverture** pour le quiz, une **illustration** et un
**chrono** (5 s à 4 min) par question, 2 à 6 propositions, réordonnancement des questions.

Les images (JPEG, PNG, GIF, WebP, 5 Mo max) sont téléversées dans `backend/public/uploads/`
sous un nom aléatoire. Un quiz ne peut référencer **que** ces chemins-là : aucune image
chargée depuis un site tiers, donc aucune fuite de l'adresse IP des joueurs.

## Partie en direct (façon Kahoot)

1. Le professeur clique **En direct** sur un quiz : un **code PIN** à 6 chiffres s'affiche, à projeter.
2. Les joueurs vont sur **Rejoindre**, saisissent le code : leur pseudo apparaît dans la salle d'attente.
3. **Lancer la partie** : la question, l'image et le chrono s'affichent sur l'écran de l'animateur ;
   chaque joueur répond sur son appareil avec des **tuiles de couleur et de forme** (▲ ◆ ● ■).
4. La question se clôt à la fin du chrono, ou dès que tout le monde a répondu :
   répartition des réponses, bonne réponse, classement provisoire.
5. Après la dernière question : **podium**. Chaque joueur retrouve la partie et sa
   correction dans **Mes parties**.

**Points** : une bonne réponse rapporte de 1 000 points (instantanée) à 500 (au dernier moment),
une mauvaise réponse 0.

Pas de WebSocket : les écrans interrogent l'état toutes les secondes. C'est simple, sans
service supplémentaire à faire tourner, et largement suffisant pour une classe.

## Données personnelles (RGPD)

| Principe | Mise en œuvre |
|---|---|
| **Information et consentement** | page **Confidentialité** (`/confidentialite`), case à cocher obligatoire à l'inscription ; date et version acceptées enregistrées |
| **Minimisation** | e-mail, pseudo, mot de passe haché, parties. Pas de nom, pas de date de naissance, pas de mesure d'audience, aucun service tiers. L'annuaire admin n'affiche pas les e-mails |
| **Droit d'accès et portabilité** | **Mon compte → Télécharger mes données** (JSON complet) |
| **Droit à l'effacement** | **Mon compte → Supprimer mon compte** : compte, jetons, historique et participations effacés ; quiz rédigés anonymisés (« compte supprimé ») |
| **Durées de conservation** | jetons 30 jours ; parties en direct 24 h ; comptes inactifs 3 ans — appliquées par `app:rgpd:purge` |
| **Sécurité** | mots de passe hachés, jetons stockés sous forme d'empreinte SHA-256, rôles vérifiés côté serveur |

À lancer chaque jour (cron) :

```bash
php bin/console app:rgpd:purge            # --dry-run pour voir ce qui serait supprimé
```

> ⚠️ Avant une mise en ligne, compléter dans `frontend/src/pages/Privacy.jsx` le **responsable
> du traitement**, son **contact** et l'**hébergeur** (marqués « à compléter »).

## API

Toutes les routes sauf `register`, `login` et `logout` demandent `Authorization: Bearer <jeton>`.

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/register` | e-mail, pseudo, mot de passe, consentement (+ clé d'accès) → jeton |
| POST | `/api/login` | e-mail + mot de passe → jeton |
| POST | `/api/logout` | révoque le jeton |
| GET / DELETE | `/api/me` | mon compte / suppression (mot de passe requis) |
| POST | `/api/me/access-key` | saisir une clé d'accès |
| GET | `/api/me/export` | export de mes données (JSON) |
| GET | `/api/quizzes` | liste, filtres `search`, `category`, `mine` |
| GET | `/api/categories` | catégories existantes |
| GET | `/api/quizzes/{id}` | détail (`?withAnswers=1` réservé à l'auteur/admin) |
| POST / PUT / DELETE | `/api/quizzes[/{id}]` | création, édition, suppression (**prof / admin**) |
| POST | `/api/uploads` | téléverser une image, champ `image` (**prof / admin**) |
| POST | `/api/ai/quizzes` | créer et publier un quiz par IA à partir d'un sujet (**prof / admin + clé IA**, 15/h) |
| GET / POST | `/api/ai-keys` | lister / générer une clé IA (**admin**) |
| DELETE | `/api/ai-keys/{id}` | révoquer une clé IA (**admin**) |
| POST | `/api/me/ai-key` | lier une clé IA à son compte |
| POST | `/api/quizzes/{id}/sessions` | partie solo : envoie les réponses, reçoit la correction |
| GET | `/api/quizzes/{id}/sessions` | classement des participants du quiz |
| POST | `/api/live-games` | créer une partie en direct (**prof / admin**) |
| GET | `/api/live-games/{pin}` | état de la partie (animateur ou joueur inscrit) |
| POST | `/api/live-games/{pin}/join` | rejoindre |
| POST | `/api/live-games/{pin}/next` | étape suivante (**animateur**) |
| POST | `/api/live-games/{pin}/answers` | répondre à la question en cours |
| DELETE | `/api/live-games/{pin}` | arrêter la partie (**animateur**) |
| GET | `/api/users` | annuaire des comptes (**admin**) |
| PUT | `/api/users/{id}/role` | changer un rôle (**admin**) |
| GET / POST | `/api/access-keys` | lister / générer une clé (**admin**) |
| DELETE | `/api/access-keys/{id}` | révoquer une clé (**admin**) |
| GET | `/api/sessions` | historique (`?all=1` pour un admin) |
| GET | `/api/sessions/{id}` | détail d'une session |
| GET | `/api/stats` | compteurs + classement |

## Tests

```bash
cd backend && php bin/phpunit   # 69 tests : comptes et RGPD, sécurité, rôles, quiz et images, parties en direct, IA
cd frontend && npm run lint && npm run build
```

## À suivre

- **Lecture vocale des quiz** : prévue, non implémentée. Le plus simple sera
  `speechSynthesis` (Web Speech API, native, sans coût) branché sur `Play.jsx`
  et `LiveHost.jsx`.
- **Modifier son e-mail ou son pseudo** depuis « Mon compte » (aujourd'hui : sur demande
  au contact indiqué dans la politique de confidentialité).
- **Mot de passe oublié** : demande un envoi d'e-mail (`symfony/mailer`).
