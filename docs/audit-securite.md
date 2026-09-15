# Audit sécurité & performances du backend — fichier de contexte

Point d'entrée pour reprendre l'audit dans une prochaine session : ce qui a été vérifié,
ce qui a été corrigé (et pourquoi ces choix), et ce qu'il reste à traiter, par priorité.
Dernière mise à jour : 2026-09-15. Détail des modifications : `docs/CHANGELOG.md`.

## Le projet en bref

- **Backend** : Symfony 8.1 / PHP 8.4+, Doctrine ORM 3, SQLite (`var/quiz_<env>.db`), API JSON
  stateless sous `/api` (jeton Bearer émis au login, seul le SHA-256 est stocké).
- **Frontend** : React (Vite), jeton stocké dans `localStorage` (`frontend/src/api.js`).
- **Rôles** : `user` (joueur), `prof`, `admin`, obtenus via des **clés d'accès**. Génération de
  quiz par IA (Groq) débloquée par des **clés IA** à nombre de générations fixe.
- **Tests** : `cd backend && vendor/bin/phpunit` (94 tests au 2026-09-15). Base : `tests/ApiTestCase.php`
  (schéma recréé à chaque test, comptes créés par la vraie inscription, une IP par compte).
- Conventions du backend : `backend/AGENTS.md` (attributs, autowiring, DTO + `MapRequestPayload`,
  migrations Doctrine, pas de `schema:update`).
- Préférences : pas de `git push` sans confirmation, pas de trailer Co-Authored-By dans les commits.

## Ce qui a été vérifié et est sain

- **Injections SQL** : aucune. Toutes les requêtes passent par le QueryBuilder avec paramètres
  liés ; les seuls `sprintf` dans du DQL n'insèrent que des constantes ou du DQL construit en interne.
- **Mass assignment** : impossible, les entrées passent par des DTO validés.
- **Dépendances** : `composer audit` sans alerte. Aucun secret dans l'historique git.
- **Contrôle d'accès** : rôles relus en base à chaque requête ; `IsGranted` sur les contrôleurs
  admin/prof ; propriété vérifiée sur quiz (`Identity::canEdit`) et sessions (`show`).
- **Uploads** : extension déduite du contenu, SVG refusé, nom aléatoire, chemins validés par regex.
- **Erreurs** : les 500 ne divulguent pas le message d'exception.

## Corrigé le 2026-09-15

| # | Problème | Correctif | Tests |
|---|---|---|---|
| 🔴1 | Le corrigé renvoyé après une partie permettait de rejouer à 100 % et d'être 1er ; durée falsifiable (0 s) ; parties illimitées | Classement = 1re partie de chaque joueur (SQL) ; `durationSeconds` 1–86400 ; limiter `quiz_sessions` 30 / 10 min | `QuizApiTest::testRankingKeepsOnlyEachPlayersFirstAttempt`, `…AbsurdDuration`, `…RateLimited` |
| 🔴2 | Énumération des comptes par le temps de réponse du login ; brute force distribué (limite par IP seulement) | Hachage factice si compte inconnu ; limiter `login_failures_per_account` 10 échecs / 15 min, remis à zéro au succès ; mot de passe ≤ 4096 | `AccountApiTest::testLoginFailuresAreAlsoLimitedPerAccountWhateverTheIp`, `…ClearsTheAccountFailures` |
| 🔴3 | Clés d'accès réutilisables à l'infini (une clé admin qui fuite = admin pour tous) ; `ADMIN_CODE` porte d'entrée permanente | **Choix validé** : clé admin à usage unique (réservation atomique `AccessKeyRepository::claim`), clé prof partageable ; `ADMIN_CODE` refusé dès qu'un admin existe | `AccountApiTest::testAnAdminKeyWorksOnlyOnce`, `…TeacherKeyCanBeShared…`, `…BootstrapKeyStops…`, `Service/AccessKeyRedeemerTest` |
| 🔴4 | Derrière un proxy, toutes les requêtes ont l'IP du proxy : 10 échecs bloquent tout le monde | Config déjà lue depuis `SYMFONY_TRUSTED_PROXIES` ; documenté dans `backend/.env`. **À renseigner au déploiement** (ne jamais faire confiance à toutes les IP) | — |
| ⚡ | Polling live (chaque écran, chaque seconde) : 1 requête SQL de plus par joueur | `LiveGameRepository::findLatestByPin` charge tout en 4 requêtes fixes ; réponses indexées par question (`indexBy`) ; double réponse simultanée → 409 au lieu de 500 | `LiveGameApiTest::testPollingTheStateCostsTheSameWhateverThePlayerCount` (6→10 requêtes avant, 4 après) |

Limite connue assumée : la limite par compte permet de bloquer la connexion d'une victime
15 min en échouant exprès (compromis classique).

## Reste à faire (par priorité)

### 🟠 Moyen

1. **Taille des quiz non bornée** : `QuizInput::questions` sans `Count(max)`, pas de `Length(max)` sur
   `description`, `QuestionInput::text`, `explanation`, `choices` (idem pour les brouillons IA).
2. **Routes sensibles sans rate limit** : `/api/me/access-key`, `/api/me/ai-key`, `DELETE /api/me`
   (vérifie le mot de passe → brute force avec un jeton volé), `/api/live-games/{pin}/join`
   (PIN à 6 chiffres balayable).
3. **Un prof peut voir les réponses du quiz d'un autre** en l'animant en direct
   (`LiveGameController::create` accepte n'importe quel `quizId`).
4. **Concurrence restante** : clé IA liée à deux comptes / `AiKey::consume()` en parallèle
   (verrou optimiste `#[ORM\Version]` ou `UPDATE … WHERE remaining_generations > 0`) ;
   pseudo : l'index unique SQLite est sensible à la casse alors que `isUsernameTaken` compare en `LOWER()`
   (ajouter une colonne normalisée unique).
5. **Jeton en `localStorage`** 30 jours, sans rotation ni « déconnecter partout » : exposé en cas de XSS.
6. **Aucun en-tête de sécurité** : HSTS, `X-Content-Type-Options: nosniff` (surtout `/uploads`), CSP, `Referrer-Policy`.
7. **Uploads** : pas de quota ni de nettoyage des fichiers orphelins.

### 🟢 Mineur

- `AiQuizGenerator::apiErrorMessage` renvoie le message d'erreur brut de Groq au client.
- `LIKE` des recherches sans échappement de `%` / `_`.
- `framework.session: true` inutile pour une API stateless.
- `APP_SECRET` vide dans `.env` (défini dans `.env.dev`) ; `APP_ENV=dev` par défaut : vigilance au déploiement.
- SQLite n'applique pas les clés étrangères : activer le middleware DBAL `EnableForeignKeys` rendrait
  inutile le nettoyage manuel (`QuizController::delete`, `AccountEraser`).
- Création d'une partie : le prof peut animer n'importe quel quiz (voir 🟠3).

### ⚡ Optimisations restantes

1. `SessionController::stats` : 500 sessions chargées (JSON `answers` compris) pour des sommes →
   agrégats SQL ; `playerCount` faux au-delà de 500 ; `leaderboard()` trié et coupé en PHP.
2. `UserController::list` : 1000 sessions chargées pour les stats par compte → un `GROUP BY s.user`.
3. `QuizController::list` : fetch-join de toutes les questions pour `questionCount` → `COUNT`, et pagination.
4. `findHistory` hydrate le JSON `answers` alors que les listes ne l'affichent pas.
5. Index manquants : `game_session.played_at`, `quiz.created_at`, `quiz.category`,
   `app_user.last_seen_at`, `api_token.expires_at`, `live_game.created_at`.
6. Listings du back-office (clés, comptes) non paginés en SQL.
7. Prod : `composer dump-autoload --classmap-authoritative`, OPcache/preload ; SQLite en mode WAL
   ou PostgreSQL pour les écritures concurrentes du live ; à terme Mercure/SSE à la place du polling.

## Pièges rencontrés (utiles pour la suite)

- `SlidingWindowLimiter::consume(0)` renvoie toujours `isAccepted() === true` : pour savoir si la limite
  est atteinte sans consommer, tester `getRemainingTokens() === 0`.
- Mesurer les requêtes SQL en test : `static::getContainer()->get('doctrine.debug_data_holder')`
  (`reset()`, puis `getData()['default']`) — voir `LiveGameApiTest::queriesFor`.
- La limite d'inscription (5 / h par IP) bloque les tests à plus de 5 comptes : `ApiTestCase::account()`
  inscrit chaque compte depuis sa propre `REMOTE_ADDR` (`request(..., server: [...])`).
- La clé de secours `admin` ne fonctionne que pour le **premier** admin : les autres admins de test
  passent par une clé admin générée par « direction ».
- Fetch-join d'une collection + `setMaxResults` tronque les lignes, pas les entités : d'où la
  recherche de l'id d'abord dans `findLatestByPin`.
- `SYMFONY_TRUSTED_HEADERS` ne doit pas être défini vide (exception « trusted header "" not supported ») ;
  non défini, Symfony utilise `X-Forwarded-For/Port/Proto`.
