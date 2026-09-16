# Changelog

Une entrée par fonctionnalité : ce qui change, où, et comment le vérifier.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).

## 2026-09-16 — Environnement de développement Docker

Lancer QuizLab sans installer PHP ni Node : `docker compose up --build`.

- `backend/Dockerfile` : PHP 8.4 CLI + `intl`, `zip`, Composer ; `backend/docker/entrypoint.sh`
  installe les dépendances si besoin et applique les migrations au démarrage.
- `frontend/Dockerfile` : Node 24 Alpine, `npm ci` seulement si `package-lock.json` a changé, Vite sur `0.0.0.0`.
- `compose.yaml` : les deux services ensemble (ports 8000 / 5173), code monté pour le
  rechargement à chaud, `node_modules` et cache Composer en volumes.
- `.dockerignore` : ni `vendor`, ni `node_modules`, ni `.env.local` dans les images.
- Documentation : section « Avec Docker » du README et formation `docs/docker.md`.

**Vérifier** : `docker compose up --build`, puis http://localhost:5173 et http://localhost:8000.

## 2026-09-15 — Audit sécurité et performances du backend (1re passe)

Audit complet du backend (OWASP, injections SQL, contrôle d'accès, performances). Aucune
injection SQL trouvée. Les 4 failles prioritaires et le coût du polling live sont corrigés ;
le reste est listé dans `docs/audit-securite.md`.

### Sécurité

- **Triche sur les classements** : la correction renvoyée après une partie donne les bonnes
  réponses, rejouer suffisait pour finir premier.
  - `GameSessionRepository::findByQuiz` ne retient que la **première partie de chaque joueur**
    (les suivantes restent dans l'historique) ; tri et limite (100) faits en SQL.
  - `PlayInput` : `durationSeconds` entre 1 et 86400, au plus 500 réponses.
  - `SessionController::play` : limiter `quiz_sessions` (30 parties / 10 min par compte).
  - `frontend/src/pages/Play.jsx` : durée envoyée d'au moins 1 s.
- **Connexion** (`AuthController::login`) :
  - hachage factice quand l'adresse est inconnue : le temps de réponse ne révèle plus quels comptes existent ;
  - nouveau limiter `login_failures_per_account` (10 échecs / 15 min par compte, quelle que soit
    l'IP, remis à zéro par une connexion réussie), en plus de la limite par IP ;
  - `LoginInput` : mot de passe limité à 4096 caractères (au-delà, le hacheur levait une exception).
- **Clés d'accès** :
  - une **clé admin est à usage unique** : elle se lie au premier compte qui la saisit, avec une
    réservation atomique en base (`AccessKeyRepository::claim`) ; les **clés prof restent partageables** ;
  - la clé de secours `ADMIN_CODE` est **refusée dès qu'un compte admin existe** (`UserRepository::hasAdmin`) ;
  - `AccessKeyRedeemer::redeem(string $code, User $user): bool` applique directement le rôle au compte
    (utilisé par `AuthController::register` et `AccountController::redeemAccessKey`).
- **Proxys de confiance** : `backend/.env` documente `SYMFONY_TRUSTED_PROXIES`, à renseigner dans
  `.env.local` derrière un reverse proxy (sinon les limites par IP s'appliquent à tout le monde à la fois).

### Performances

- **Polling des parties en direct** : `LiveGameRepository::findLatestByPin` charge animateur, quiz,
  questions, joueurs et réponses en **4 requêtes SQL fixes** (avant : 6 avec 1 joueur, 10 avec 5).
- `LivePlayer::$answers` indexées par numéro de question (`indexBy`, sans changement de schéma).
- `SessionController::stats` compte les quiz avec `count()` au lieu de tous les charger.

### Corrigé

- Deux réponses simultanées à la même question en direct renvoient 409 au lieu d'une erreur 500
  (`LiveGameEngine::answer`).

### Tests

- 12 nouveaux tests (94 au total) : classement sur la 1re partie, durée absurde, limite des parties,
  limite de connexion par compte et remise à zéro, clé admin à usage unique, clé prof partagée,
  clé de secours refusée une fois un admin inscrit, coût SQL du polling indépendant du nombre de joueurs.
- `ApiTestCase` : chaque compte s'inscrit depuis sa propre IP ; les admins autres que « direction »
  passent par une clé admin générée ; `request()` accepte des paramètres serveur.
- `Service/AccessKeyRedeemerTest` réécrit pour la nouvelle signature.

### Documentation

- `docs/administration.md` : clé admin à usage unique, rôle limité de la clé de secours.
- `docs/audit-securite.md` : fichier de contexte de l'audit (vérifié, corrigé, reste à faire, pièges).

### À savoir

- La clé admin des fixtures (`2MZL-SEPP-F67F-9TGN`) ne sert plus qu'**une fois** par chargement des fixtures.
- En dev, `ADMIN_CODE` ne donne plus le rôle admin si la base contient déjà un admin
  (c'est le cas avec les fixtures) : utiliser `php bin/console app:access-key admin`.

### Vérifier

```bash
cd backend
vendor/bin/phpunit
php bin/console doctrine:schema:validate
```

## 2026-09-15 — Logo et illustrations

### Ajouté

- `frontend/src/assets/logo-mark.svg` : logo QuizLab — bulle de discussion (le côté
  social) contenant un Q dont la queue est une coche (la bonne réponse).
- `frontend/src/components/Brand.jsx` : logo + nom « Quiz**Lab** », utilisé dans la barre
  du haut, la connexion et la page confidentialité (remplace le carré « Q »).
- Illustrations SVG dans `frontend/src/assets/`, aux couleurs du thème :
  - `hero-study.svg` (livres, ampoule, bulles) : bandeau de l'accueil ;
  - `classroom.svg` (trois élèves qui échangent) : page de connexion ;
  - `community.svg` (camarades reliés) : en-tête de la page Communauté ;
  - `empty-notebook.svg` (cahier et loupe) : états vides (composant `Empty`).
- `index.html` : meta `description` et `theme-color`.

### Modifié

- `frontend/public/favicon.svg` : le logo QuizLab remplace le favicon par défaut de Vite.
- `index.html` : titre « QuizLab — apprendre ensemble ».
- `styles.css` : `.brand-mark` devient une image ; section « Illustrations »
  (masquées sous 1100 px pour l'accueil, sous 900 px pour la connexion et la Communauté).

### Vérifier

```bash
cd frontend
npm run lint && npm run build
npm run dev   # accueil, /login, /communaute, et une recherche sans résultat
```

## 2026-09-15 — Redesign : apprendre et jouer ensemble

L'interface met en avant l'apprentissage et la classe. Frontend uniquement : aucune
route d'API ajoutée ni modifiée.

### Ajouté

- Page **Communauté** (`/communaute`, `pages/Community.jsx`) : chiffres de la classe,
  classement général, nouveaux quiz à relever, auteurs qui partagent le plus.
- `frontend/src/progress.js` : calculs de progression partagés (meilleur score et dernière
  partie par quiz, quiz maîtrisés ≥ 80 %, à revoir < 50 %).
- `components/community.jsx` : classement général, chiffres de la classe, colonne de l'accueil.
- `components/JoinForm.jsx` : saisie du code PIN, partagée par l'accueil et **Rejoindre**.
- `components/ui.jsx` : `Subject` (icône et teinte par matière), `Avatar` (teinte stable par
  pseudo), `subjectOf`, `hueOf`.

### Modifié

- **Thème** : le thème sombre devient un thème clair façon cahier (fond papier quadrillé,
  ombres douces). Les couleurs passent par les variables CSS, donc le back-office et
  les parties en direct suivent sans autre changement.
- **Accueil** (`Library.jsx`) : bandeau de progression (parties, réussite moyenne, quiz
  maîtrisés, quiz à revoir), accès direct à une partie en direct par code PIN, filtres
  par matière, colonne « classement général ». Les cartes de quiz indiquent où on en est
  (Maîtrisé / En progrès / À revoir) et le bouton « En direct » devient « Lancer en classe ».
- **Bilan de partie** (`Result.jsx`) : les erreurs d'abord (« À retenir » : ta réponse,
  la bonne, l'explication), les bonnes réponses repliées, l'écart avec la tentative
  précédente et la place dans le classement de la classe.
- **Mon parcours** (`History.jsx`) : maîtrise par matière, quiz à retravailler, journal.
- **Classement d'un quiz** (`Scores.jsx`, `Ranking.jsx`) : podium des 3 meilleurs joueurs,
  avatars, lignes du joueur connecté signalées « toi ». `Ranking` accepte les parties
  déjà chargées (`sessions`) pour éviter une requête en double.
- **Connexion** (`Login.jsx`) : présentation de la plateforme à côté du formulaire.
- **Navigation** : Accueil / Rejoindre / Mon parcours / Communauté ; le rôle « joueur »
  s'affiche « élève ».
- `README.md` : description de l'interface.

### Supprimé

- Styles `.review` / `.review-item`, remplacés par `.lesson`.

### Vérifier

```bash
cd frontend
npm run lint && npm run build
npm run dev   # jouer un quiz, puis consulter le bilan, Mon parcours et Communauté
```

## 2026-09-15 — Données de démonstration (fixtures)

### Ajouté

- `doctrine/doctrine-fixtures-bundle` (dev/test uniquement, installé via Flex).
- `src/DataFixtures/` : une fixture par fonctionnalité, reliées par dépendances et groupes.
  - `UserFixtures` : 8 comptes (admin, 2 profs, 5 joueurs), mot de passe `quizlab-demo`.
  - `AccessKeyFixtures` : clés d'accès dans les 4 états ; reprend les clés documentées
    dans `administration.md`, qui restent donc valables après rechargement.
  - `AiKeyFixtures` : clés IA dans les 5 états filtrables du back-office.
  - `QuizFixtures` : 4 quiz créés via `QuizWriter`.
  - `GameSessionFixtures` : 10 parties solo corrigées via `SessionGrader`.
  - `LiveGameFixtures` : une partie en direct terminée et une salle d'attente, via `LiveGameEngine`.
- `src/Service/SessionGrader.php` : correction d'une partie solo.
- `tests/DataFixturesTest.php` : le jeu chargé, le site est utilisable (connexion, bibliothèque, historique, stats, clés).
- `docs/fixtures.md` : comptes, clés, contenu, architecture.

### Modifié

- `SessionController::play` délègue la correction à `SessionGrader` (logique déplacée à
  l'identique, sans changement de comportement de l'API) : contrôleur plus fin, et les
  fixtures corrigent les parties exactement comme l'API.
- `README.md` : démarrage avec les fixtures.

### Supprimé

- `src/DataFixtures/AppFixtures.php` (squelette vide créé par la recipe).

### Vérifier

```bash
cd backend
php bin/console doctrine:fixtures:load --no-interaction
php bin/phpunit
```
