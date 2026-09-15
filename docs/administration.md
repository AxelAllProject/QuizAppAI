# Rôles et clés d'accès

## Les trois rôles

| Rôle | Comment on l'obtient | Ce qu'il permet |
|---|---|---|
| **Joueur** | un compte (e-mail + mot de passe), sans rien d'autre | parcourir la bibliothèque, jouer en solo, rejoindre les parties en direct, voir sa correction, les classements, son historique |
| **Professeur** | compte + **clé professeur** | tout ce qui précède + **créer, modifier et supprimer ses propres quiz**, **animer des parties en direct** |
| **Administrateur** | compte + **clé administrateur** | tout ce qui précède + **modérer tous les quiz**, **générer et révoquer les clés**, **changer le rôle des comptes**, tableau de bord |

Une clé n'est pas un compte : elle donne un rôle **au compte qui la saisit**. Plusieurs
professeurs peuvent utiliser la même **clé professeur** — chacun garde son propre compte,
qui signe les quiz qu'il rédige. Une **clé administrateur**, elle, ne sert **qu'une fois** :
elle se lie au premier compte qui la saisit et passe à l'état « attribuée ». Un code admin
qui circulerait ne ferait donc administrateur personne d'autre. Une clé ne fait que
**monter** en grade : un administrateur qui saisit une clé professeur reste administrateur.

## Les clés en place

| Rôle | Clé | Étiquette |
|---|---|---|
| **Administrateur** | `2MZL-SEPP-F67F-9TGN` | Compte administrateur principal |
| **Professeur** | `6YW5-4X49-2FFX-NB5P` | Équipe pédagogique |

Il existe en plus une **clé de secours** définie dans l'environnement
(`ADMIN_CODE` dans `backend/.env.local`) : `XPYX-KDEU-2RLE-97PV`. Elle sert uniquement
à créer le **premier** administrateur : dès qu'un compte admin existe, elle est refusée
(les admins émettent ensuite des clés révocables depuis le back-office). Si plus aucun
compte admin n'existe, elle refonctionne. La clé admin de la base ci-dessus est à usage
unique : une fois saisie, il faut en générer une autre.

> Ces clés sont des identifiants de **développement local**. Ne les recopie pas dans
> un dépôt public : régénère-les avant toute mise en ligne.

## Utiliser une clé

**À l'inscription**

1. Ouvrir http://localhost:5173 → onglet **Créer un compte**
2. Renseigner e-mail, pseudo (ex. `mme.martin`) et mot de passe
3. Cliquer sur **« J'ai une clé d'accès (professeur ou admin) »** et coller la clé
4. Accepter la politique de confidentialité, puis **Créer mon compte**

**Avec un compte existant** : **Mon compte → Clé d'accès**, coller la clé, **Valider la clé**.

Le rôle s'affiche sous le pseudo en haut à droite. Le menu **Créer** et le bouton
**En direct** apparaissent pour les professeurs et les admins ; **Administration**,
pour les admins seulement.

## Le back-office

**Administration** ouvre un back-office à cinq sections, chacune sur sa propre
adresse — on peut donc mettre une section en favori ou la recharger sans perdre sa place :

| Section | Adresse | Ce qu'on y fait |
|---|---|---|
| **Vue d'ensemble** | `/admin` | chiffres de la plateforme, meilleurs joueurs, dernières parties |
| **Comptes** | `/admin/comptes` | chercher un compte, filtrer par rôle, accorder ou retirer les droits |
| **Clés d'accès** | `/admin/cles` | émettre, attribuer, filtrer et révoquer les clés prof / admin |
| **Clés IA** | `/admin/cles-ia` | émettre, lier, filtrer et révoquer les clés de génération |
| **Parties** | `/admin/parties` | classement complet et historique de toutes les parties |

Les trois listings se filtrent **côté serveur** : la recherche et les filtres sont
envoyés à l'API, qui ne renvoie que les lignes correspondantes. L'écran tient donc
quand la base grossit, au lieu de charger toute la table pour la trier ensuite.

## Générer et révoquer des clés depuis l'interface (admin)

**Administration → Clés d'accès** :

- choisir **Professeur** ou **Administrateur** ;
- soit **chercher un compte existant** par pseudo dans le champ **Attribuer à** et
  cliquer **Attribuer le rôle** : le rôle est accordé tout de suite à cette personne,
  sans code à transmettre — la ligne affiche « attribuée à … » ;
- soit laisser ce champ vide, choisir une **expiration** (sans expiration, 1 / 7 / 30 /
  90 jours, 1 an), ajouter une étiquette (« Mme Martin », « Direction »…) et cliquer
  **Générer une clé** : le code s'affiche en clair juste au-dessus du tableau, prêt à
  être copié, pour être transmis à la personne qui le saisira elle-même (à l'inscription
  ou dans **Mon compte**) ;
- **Révoquer** désactive un code : il ne peut plus être saisi. La ligne reste visible,
  grisée, avec son nombre d'utilisations.

**Expiration** : passée la date, le code cesse de conférer le rôle — exactement comme
s'il était révoqué — et la ligne passe à l'état « périmée ». C'est ce qu'il faut pour un
code distribué à l'oral en début d'année : il ne traîne pas indéfiniment. Les rôles
**déjà accordés** par ce code ne sont pas repris pour autant.

Chaque clé affiche un **état** unique, sur lequel on peut filtrer :

| État | Ce que ça veut dire |
|---|---|
| **active** | utilisable : ni attribuée, ni périmée, ni révoquée |
| **attribuée** | déjà donnée directement à un compte ; le code ne resservira pas |
| **périmée** | la date d'expiration est passée |
| **révoquée** | désactivée à la main par un administrateur |

Révoquer une clé **ne retire pas** le rôle aux comptes qui l'ont déjà utilisée.
Pour cela : **Administration → Comptes**, changer le rôle dans la liste. L'effet est
**immédiat**, sans que la personne ait à se déconnecter (le rôle est relu en base à
chaque requête). Un administrateur ne peut pas retirer ses propres droits.

## Clés IA (fonctionnalité premium)

Distinctes des clés d'accès : avoir le rôle professeur ou administrateur ne suffit pas
pour générer un quiz par IA, il faut **aussi** une clé IA. Une clé IA donne un nombre fixe
de générations (5 par défaut) et peut expirer ; elle se lie au **premier compte** qui la
saisit et personne d'autre ne peut ensuite l'utiliser.

**Administration → Clés IA** : choisir le nombre de générations et, si besoin, une
expiration. Comme pour les clés d'accès, chercher un compte existant et cliquer
**Lier la clé** la rattache tout de suite à cette personne ; sinon, ajouter une étiquette et
cliquer **Générer une clé** produit un code que la personne saisit elle-même dans
**Mon compte → Clé IA**. Le filtre d'état distingue les clés **actives**, **à distribuer**
(émises mais liées à personne), **épuisées**, **périmées** et **révoquées**. **Révoquer** coupe l'accès immédiatement, y compris pour les
générations déjà consommées ; ça n'efface pas l'historique.

Supprimer le compte qui détenait une clé IA la libère (redevient saisissable par
quelqu'un d'autre), mais **ne restaure pas** les générations déjà consommées.

## Générer des clés en ligne de commande

```bash
cd backend

php bin/console app:access-key --list                        # toutes les clés et leur état
php bin/console app:access-key prof  --label="Mme Martin"    # nouvelle clé professeur
php bin/console app:access-key admin --label="Direction"     # nouvelle clé administrateur
php bin/console app:access-key prof  --expires=30            # clé professeur valable 30 jours

php bin/console app:admin-key                                # régénère la clé de secours (.env.local)
php bin/console app:admin-key --show                         # affiche la clé de secours
```

Format `XXXX-XXXX-XXXX-XXXX`, tiré par `random_int` (source cryptographique) dans un
alphabet **sans caractères ambigus** — ni `O`/`0`, ni `I`/`1` — pour se dicter sans erreur.

Changer la clé de secours demande un **redémarrage du serveur**
(`symfony server:stop && symfony server:start -d`) ; les clés créées en base sont
actives immédiatement, sans redémarrage.

## Données personnelles

- **Purge quotidienne** à planifier (cron) : `php bin/console app:rgpd:purge`
  — jetons expirés, parties en direct de plus de 24 h, comptes inactifs depuis 3 ans.
  `--dry-run` affiche ce qui serait supprimé.
- Une personne qui demande l'effacement de son compte peut le faire elle-même
  (**Mon compte → Supprimer mon compte**). Pour une demande de rectification
  (e-mail, pseudo), écrire directement en base ou supprimer puis recréer le compte.
- Avant mise en ligne : compléter le responsable du traitement, le contact et
  l'hébergeur dans `frontend/src/pages/Privacy.jsx`.
