# Données de démonstration (fixtures)

Jeu de données complet pour tester le site sans rien saisir à la main : comptes de
chaque rôle, bibliothèque de quiz, parties solo, parties en direct, clés d'accès et
clés IA dans tous leurs états.

> **Attention :** `doctrine:fixtures:load` **vide la base** avant de la remplir.
> Sauvegarde `backend/var/quiz_dev.db` si elle contient des données à garder.

## Charger

```bash
cd backend
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction          # tout le jeu « demo »
php bin/console doctrine:fixtures:load --group=quizzes           # une seule fonctionnalité (+ ses dépendances)
```

Le bundle n'est actif qu'en `dev` et `test` : impossible de lancer les fixtures en production.

## Comptes

Mot de passe commun : **`quizlab-demo`**. E-mail : `<pseudo>@quizlab.test`.

| Pseudo | Rôle | Pour tester |
|---|---|---|
| `direction` | administrateur | back-office, modération, émission de clés |
| `mme.martin` | professeur | ses quiz, animation d'une partie terminée, clé IA active (8/10) |
| `m.durand` | professeur | ses quiz, salle d'attente ouverte, clé IA épuisée |
| `lea`, `hugo`, `nina`, `tom`, `sarah` | joueur | historiques, classements, podium |

## Contenu

| Fonctionnalité | Fixture | Groupe | Données |
|---|---|---|---|
| Comptes | `UserFixtures` | `accounts` | 8 comptes (1 admin, 2 profs, 5 joueurs) |
| Clés d'accès | `AccessKeyFixtures` | `access-keys` | active admin, active prof, périmée, révoquée, attribuée |
| Clés IA | `AiKeyFixtures` | `ai-keys` | active, épuisée, à distribuer, périmée, révoquée |
| Quiz | `QuizFixtures` | `quizzes` | 4 quiz (culture, web, sciences, histoire) |
| Parties solo | `GameSessionFixtures` | `sessions` | 10 parties corrigées |
| Parties en direct | `LiveGameFixtures` | `live-games` | 1 partie terminée (4 joueurs), 1 salle d'attente (2 joueurs) |

Clés utiles :

| Clé | Effet |
|---|---|
| `2MZL-SEPP-F67F-9TGN` | rôle administrateur — **usage unique** : une seule saisie par chargement des fixtures |
| `6YW5-4X49-2FFX-NB5P` | rôle professeur (partageable) |
| `DEMO-AIKY-FREE-2026` | clé IA de 5 générations, libre (à saisir dans **Mon compte → Clé IA**) |

## Architecture

```
backend/src/DataFixtures/
├── UserFixtures.php          comptes (mot de passe haché par le hasher de Security)
├── AccessKeyFixtures.php     dépend de UserFixtures
├── AiKeyFixtures.php         dépend de UserFixtures
├── QuizFixtures.php          → Service\QuizWriter (même chemin que l'éditeur)
├── GameSessionFixtures.php   → Service\SessionGrader (même correction que l'API)
└── LiveGameFixtures.php      → Service\LiveGameEngine (partie jouée de bout en bout)
```

Principes :

- **Une fixture par fonctionnalité**, reliées par `DependentFixtureInterface` et des
  références nommées (`UserFixtures::ref('lea')`, `QuizFixtures::ref('web')`).
- **Pas de chemin parallèle** : les fixtures passent par les services métier
  (`QuizWriter`, `SessionGrader`, `LiveGameEngine`) et les méthodes des entités
  (`assignTo`, `redeemFor`, `consume`, `revoke`). Une donnée de démo respecte donc
  exactement les mêmes règles qu'une donnée créée depuis le site.
- **Groupes** (`FixtureGroupInterface`) : `demo` pour tout, un groupe par fonctionnalité.
- **Testé** : `tests/DataFixturesTest.php` charge le jeu complet et vérifie connexion par
  rôle, bibliothèque, historiques, statistiques, clé professeur et clé IA.

`app:seed` reste disponible pour ajouter les 3 quiz d'origine **sans vider** la base.
